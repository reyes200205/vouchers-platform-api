<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

final class AuditLogger
{
    /**
     * @param array<string, mixed>|null $extraData
     * @param array<string, mixed>|null $oldData  Estado anterior al cambio (solo para updates)
     */
    public function record(
        Request $request,
        AuditEventType $eventType,
        string $module,
        string $description,
        ?int $branchId = null,
        ?array $extraData = null,
        ?string $level = null,
        ?array $oldData = null
    ): void {
        /** @var User|null $user */
        $user = $request->user();

        $data = [
            'event_type' => $eventType->value,
            'user_id' => $user?->id,
            'user_name' => $user?->username,
            'user_role' => $user?->businessRoles()->wherePivotNull('revoked_at')->value('roles.code'),
            'branch_id' => $branchId,
            'module' => $module,
            'description' => $description,
            'extra_data' => $extraData,
            'old_data' => $oldData,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if ($level !== null) {
            $data['level'] = $level;
        }

        AuditLog::query()->create($data);
    }
}
