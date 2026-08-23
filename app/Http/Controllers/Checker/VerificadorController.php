<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checker;

use App\Enums\ApplicationStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\StoreApplicationVerificationRequest;
use App\Http\Requests\Applications\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationVerification;
use App\Models\User;
use App\Notifications\ApplicationVerifiedByVerifierNotification;
use App\Services\Applications\UpdateApplicationService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

final class VerificadorController extends ApiController
{
    /**
     * Corrige datos mal capturados por el coordinador (dirección, teléfono,
     * CURP, etc.) antes de registrar la verificación en sitio. Ver
     * UpdateApplicationService para las reglas de cuándo procede.
     */
    public function update(UpdateApplicationRequest $request, Application $application, UpdateApplicationService $service, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validated();

        // Mismo caso que CoordinadorController::store(): validated() poda las
        // sub-claves de family_data que no tienen regla propia (members,
        // occupation, housing), dejando solo applicant_age. Se restaura desde
        // el input crudo para no perder la correccion completa del verificador.
        if (array_key_exists('family_data', $validated)) {
            $validated['family_data'] = $request->input('family_data');
        }

        $oldPersonSnapshot = $application->applicant?->only(array_keys($validated['person'] ?? []));

        $updated = $service->execute($request->user(), $application, $validated);

        // El diff legible (con etiquetas) queda en verifier_corrections_json
        // para que gerencia lo vea al decidir; aquí se deja el snapshot crudo
        // como old_data del log, consistente con el resto de los módulos.
        $audit->record(
            $request,
            'APPLICATION_UPDATED',
            'applications',
            'Datos de la solicitud corregidos por el verificador.',
            $application->branch_id,
            ['application_id' => $application->id, 'new_person_data' => $validated['person'] ?? null],
            null,
            $oldPersonSnapshot
        );

        return $this->success($updated);
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

        if ($application->coordinator) {
            Notification::send($application->coordinator, new ApplicationVerifiedByVerifierNotification($application, $verification));
        }

        return $this->success($verification->load('application'));
    }
}
