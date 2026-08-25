<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\DecideApplicationRequest;
use App\Models\Application;
use App\Models\User;
use App\Services\Applications\DecideApplicationService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

final class ApplicationDecisionController extends ApiController
{
    public function decide(DecideApplicationRequest $request, Application $application, DecideApplicationService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $service->execute($application, $user, $request->validated());
        $distributor = $result['distributor'];

        $audit->record(
            $request,
            AuditEventType::Decided,
            'applications',
            'Decision final de solicitud registrada.',
            $application->branch_id,
            [
                'application_id' => $application->id,
                'decision' => $request->string('decision')->value(),
                'rejection_reason' => $request->string('rejection_reason')->value() ?: null,
                'distributor_id' => $distributor?->id,
                'credit_limit' => $distributor?->credit_limit,
                'category_id' => $distributor?->category_id,
                'coordinator_user_id' => $distributor?->coordinator_user_id,
            ]
        );

        return $this->success([
            'application' => $result['application'],
            'distributor' => $result['distributor'],
            'distributor_username' => $result['distributor_username'],
            // Contrasena temporal en texto plano: solo se muestra en esta respuesta,
            // nunca se persiste ni se vuelve a exponer. El gerente debe comunicarla
            // a la distribuidora para su primer inicio de sesion.
            'temporary_password' => $result['temporary_password'],
        ]);
    }
}