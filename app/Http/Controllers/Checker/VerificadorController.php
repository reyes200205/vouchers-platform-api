<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checker;

use App\Enums\ApplicationStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\StoreApplicationVerificationRequest;
use App\Models\Application;
use App\Models\ApplicationVerification;
use App\Models\User;
use App\Notifications\ApplicationVerifiedByVerifierNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

final class VerificadorController extends ApiController
{
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
