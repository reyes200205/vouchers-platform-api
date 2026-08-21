<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\ApiController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with(['branch'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            AuditLogResource::collection($logs)->response()->getData(true)
        );
    }
}
