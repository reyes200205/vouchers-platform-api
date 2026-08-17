<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends ApiController
{
    public function index(Request $request, DashboardStatsService $service): JsonResponse
    {
        $user = $request->user();

        $branchId = $request->integer('branch_id', 0) ?: null;

        if ($branchId && $user && ! $user->hasGlobalBusinessRole()) {
            $allowedBranchIds = $user->activeBusinessBranchIds();
            if (! in_array($branchId, $allowedBranchIds, true)) {
                return $this->forbidden('No tienes acceso a esa sucursal.');
            }
        }

        return $this->success($service->all($branchId));
    }
}