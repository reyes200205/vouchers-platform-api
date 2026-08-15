<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Models\Application;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CoordinadorController extends ApiController
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
}
