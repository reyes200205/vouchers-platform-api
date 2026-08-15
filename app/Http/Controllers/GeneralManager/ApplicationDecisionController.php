<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

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
        $audit->record($request, 'APPLICATION_DECIDED', 'applications', 'Decision final de solicitud registrada.', $application->branch_id, ['application_id' => $application->id, 'decision' => $request->string('decision')->value()]);

        return $this->success([
            'application' => $result['application'],
            'distributor' => $result['distributor'],
            'distributor_username' => $result['distributor_username'],
        ]);
    }
}