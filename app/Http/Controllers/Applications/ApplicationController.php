<?php

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\DecideApplicationRequest;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Http\Requests\Applications\StoreApplicationVerificationRequest;
use App\Models\Application;
use App\Models\ApplicationVerification;
use App\Models\Person;
use App\Models\User;
use App\Services\Applications\DecideApplicationService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApplicationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $applications = Application::query()
            ->with(['applicant', 'branch', 'assignedVerifier', 'verification'])
            ->when(! $user->hasGlobalBusinessRole(), fn ($query) => $query->whereIn('branch_id', $user->activeBusinessBranchIds()))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success($applications);
    }

    public function store(StoreApplicationRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! $user->hasBusinessAbility('applications.create', $data['branch_id'])) {
            return $this->forbidden();
        }

        $application = DB::transaction(function () use ($data, $user): Application {
            $person = Person::query()->create($data['person']);

            return Application::query()->create([
                'applicant_person_id' => $person->id,
                'branch_id' => $data['branch_id'],
                'captured_by_user_id' => $user->id,
                'coordinator_user_id' => $user->id,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'status' => ApplicationStatus::EN_REVISION,
                'initial_category_code' => $data['initial_category_code'] ?? 'COPPER',
                'family_data_json' => $data['family_data'] ?? null,
                'external_affiliations_json' => $data['external_affiliations'] ?? null,
                'vehicles_json' => $data['vehicles'] ?? null,
                'requested_credit_limit' => $data['requested_credit_limit'] ?? null,
                'id_front_path' => $data['id_front_path'] ?? null,
                'id_back_path' => $data['id_back_path'] ?? null,
                'proof_of_address_path' => $data['proof_of_address_path'] ?? null,
                'credit_bureau_report_path' => $data['credit_bureau_report_path'] ?? null,
                'credit_bureau_result' => $data['credit_bureau_result'] ?? null,
                'house_photos_complete' => $data['house_photos_complete'] ?? false,
                'taken_at' => now(),
                'submitted_at' => now(),
            ]);
        });

        $audit->record($request, 'APPLICATION_SUBMITTED', 'applications', 'Solicitud de distribuidora enviada a verificacion.', $application->branch_id, ['application_id' => $application->id]);

        return $this->created($application->load(['applicant', 'branch']));
    }

    public function assignVerifier(Request $request, Application $application, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['verifier_user_id' => ['required', 'integer', 'exists:users,id']]);
        $application->update(['assigned_verifier_id' => $data['verifier_user_id'], 'reviewed_at' => now()]);
        $audit->record($request, 'APPLICATION_VERIFIER_ASSIGNED', 'applications', 'Verificador asignado a solicitud.', $application->branch_id, ['application_id' => $application->id]);

        return $this->success($application->fresh());
    }

    public function verify(StoreApplicationVerificationRequest $request, Application $application, AuditLogger $audit): JsonResponse
    {
        if ($application->status !== ApplicationStatus::EN_REVISION) {
            return $this->error('The application is not pending verification.', 422);
        }

        /** @var User $user */
        $user = $request->user();

        if ($application->assigned_verifier_id !== $user->id) {
            return $this->forbidden();
        }

        $data = $request->validated();
        $verification = ApplicationVerification::query()->updateOrCreate(
            ['application_id' => $application->id],
            [
                'verifier_user_id' => $user->id,
                'result' => $data['result'],
                'notes' => $data['notes'] ?? null,
                'verification_latitude' => $data['verification_latitude'] ?? null,
                'verification_longitude' => $data['verification_longitude'] ?? null,
                'visit_date' => $data['visit_date'],
                'checklist_json' => $data['checklist'] ?? null,
                'justifications_json' => $data['justifications'] ?? null,
                'front_photo' => $data['front_photo'] ?? null,
                'id_with_person_photo' => $data['id_with_person_photo'] ?? null,
                'proof_of_address_photo' => $data['proof_of_address_photo'] ?? null,
                'additional_evidence_json' => $data['additional_evidence'] ?? null,
                'distance_meters' => $data['distance_meters'] ?? null,
            ]
        );

        $application->update([
            'status' => $verification->result === VerificationResult::VERIFICADA
                ? ApplicationStatus::POSIBLE_DISTRIBUIDORA
                : ApplicationStatus::RECHAZADA,
            'rejection_reason' => $verification->result === VerificationResult::RECHAZADA ? $verification->notes : null,
            'reviewed_at' => now(),
        ]);

        $audit->record($request, 'APPLICATION_VERIFIED', 'applications', 'Verificacion de solicitud registrada.', $application->branch_id, ['application_id' => $application->id, 'result' => $verification->result->value]);

        return $this->success($verification->load('application'));
    }

    public function decide(DecideApplicationRequest $request, Application $application, DecideApplicationService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $service->execute($application, $user, $request->validated());
        $audit->record($request, 'APPLICATION_DECIDED', 'applications', 'Decision final de solicitud registrada.', $application->branch_id, ['application_id' => $application->id, 'decision' => $request->string('decision')->value()]);

        return $this->success([
            'application' => $result['application'],
            'distributor' => $result['distributor'],
            'distributor_username' => $result['distributor_username'],
        ]);
    }
}
