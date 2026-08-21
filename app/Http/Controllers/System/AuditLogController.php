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
        $query = AuditLog::query()
            ->with(['branch']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            AuditLogResource::collection($logs)->response()->getData(true)
        );
    }
}
