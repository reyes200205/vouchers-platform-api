<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Models\Application;
use App\Models\Person;
use App\Models\User;
use App\Notifications\ApplicationAssignedToVerifierNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Storage\SpacesStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CoordinadorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $applications = Application::query()
            ->with(['applicant', 'branch', 'assignedVerifier.person', 'verification'])
            ->when(! $user->hasGlobalBusinessRole(), fn ($query) => $query->whereIn('branch_id', $user->activeBusinessBranchIds()))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success($applications);
    }

    /**
     * Detalle completo de una solicitud: datos del solicitante, evidencia
     * capturada por el coordinador (INE, comprobante de domicilio) y, si ya
     * se visitó, la verificación en sitio (incluida la foto de fachada). Se
     * usa desde la Bandeja de Aprobaciones al decidir una solicitud.
     */
    public function show(Request $request, Application $application, SpacesStorageService $storage): JsonResponse
    {
        $application->load([
            'applicant',
            'branch',
            'coordinator.person',
            'assignedVerifier.person',
            'verification.verifier.person',
        ]);

        // id_front_path / id_back_path / proof_of_address_path aún no tienen un
        // endpoint de carga (el formulario del coordinador siempre los manda en
        // null); se mantienen como ruta local por ahora. Las fotos de la visita
        // del verificador (verification->*_photo) sí se guardan en Spaces desde
        // VerificationPhotoController, así que su URL debe salir firmada de ahí,
        // no como ruta de /storage local.
        $baseUrl = $request->getSchemeAndHttpHost().'/storage/';
        $toUrl = fn (?string $path): ?string => $path ? $baseUrl.$path : null;

        $data = $application->toArray();
        $data['id_front_url'] = $toUrl($application->id_front_path);
        $data['id_back_url'] = $toUrl($application->id_back_path);
        $data['proof_of_address_url'] = $toUrl($application->proof_of_address_path);

        if ($application->verification !== null) {
            $data['verification']['front_photo_url'] = $storage->temporaryUrlFor($application->verification->front_photo);
            $data['verification']['id_with_person_photo_url'] = $storage->temporaryUrlFor($application->verification->id_with_person_photo);
            $data['verification']['proof_of_address_photo_url'] = $storage->temporaryUrlFor($application->verification->proof_of_address_photo);
        }

        return $this->success($data);
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
                'vehicles_json' => $data['vehicles'] ?? null,
                'requested_credit_limit' => $data['requested_credit_limit'] ?? null,
                'id_front_path' => $data['id_front_path'] ?? null,
                'id_back_path' => $data['id_back_path'] ?? null,
                'proof_of_address_path' => $data['proof_of_address_path'] ?? null,
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

        /** @var User $verifier */
        $verifier = User::query()->findOrFail($data['verifier_user_id']);

        $belongsToApplicationBranch = $verifier->businessRoles()
            ->where('roles.name', 'verifier')
            ->whereNull('model_has_roles.revoked_at')
            ->where('model_has_roles.branch_id', $application->branch_id)
            ->exists();

        if (! $belongsToApplicationBranch) {
            return $this->error('El verificador debe pertenecer a la misma sucursal de la solicitud.', 422);
        }

        $application->update(['assigned_verifier_id' => $data['verifier_user_id'], 'reviewed_at' => now()]);
        $audit->record($request, 'APPLICATION_VERIFIER_ASSIGNED', 'applications', 'Verificador asignado a solicitud.', $application->branch_id, ['application_id' => $application->id]);

        $application = $application->fresh() ?? $application;
        Notification::send($verifier, new ApplicationAssignedToVerifierNotification($application));

        return $this->success($application);
    }
}
