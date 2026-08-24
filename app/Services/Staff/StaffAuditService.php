<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

/**
 * Centraliza los registros de auditoría para el módulo de personal,
 * manteniendo el StaffController limpio de lógica de logging.
 */
final class StaffAuditService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Registra la creación de un miembro del personal.
     */
    public function recordCreated(Request $request, User $staff, string $roleCode, int|null $branchId): void
    {
        $this->audit->record(
            $request,
            AuditEventType::Created,
            'staff',
            'Miembro del personal creado.',
            $branchId,
            [
                'staff_id'  => $staff->id,
                'username'  => $staff->username,
                'is_active' => $staff->is_active,
                'role_code' => $roleCode,
                'branch_id' => $branchId,
                'person'    => $this->personSnapshot($staff),
            ]
        );
    }

    /**
     * Registra la actualización de un miembro del personal.
     *
     * @param array<string, mixed> $oldSnapshot  Snapshot capturado ANTES del update
     */
    public function recordUpdated(Request $request, User $staff, array $oldSnapshot): void
    {
        $newRole = $staff->businessRoles()->wherePivotNull('revoked_at')->first();

        $this->audit->record(
            $request,
            AuditEventType::Updated,
            'staff',
            'Miembro del personal actualizado.',
            $staff->activeBusinessBranchIds()[0] ?? null,
            [
                'staff_id'  => $staff->id,
                'username'  => $staff->username,
                'is_active' => $staff->is_active,
                'role_code' => $newRole?->code,
                'branch_id' => $newRole?->pivot?->branch_id,
                'person'    => $this->personSnapshot($staff),
            ],
            null,        // level — usa el default INFO
            $oldSnapshot // old_data: estado anterior al cambio
        );
    }

    /**
     * Captura el estado actual de un User para guardarlo como snapshot (old_data).
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $primaryRole = $user->businessRoles()->wherePivotNull('revoked_at')->first();

        return [
            'username'  => $user->username,
            'is_active' => $user->is_active,
            'role_code' => $primaryRole?->code,
            'branch_id' => $primaryRole?->pivot?->branch_id,
            'person'    => $this->personSnapshot($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personSnapshot(User $user): array
    {
        return [
            'first_name'       => $user->person?->first_name,
            'middle_name'      => $user->person?->middle_name,
            'last_name'        => $user->person?->last_name,
            'second_last_name' => $user->person?->second_last_name,
            'gender'           => $user->person?->gender,
            'birth_date'       => $user->person?->birth_date,
            'curp'             => $user->person?->curp,
            'rfc'              => $user->person?->rfc,
            'mobile_phone'     => $user->person?->mobile_phone,
            'email'            => $user->person?->email,
            'street'           => $user->person?->street,
            'external_number'  => $user->person?->external_number,
            'neighborhood'     => $user->person?->neighborhood,
            'city'             => $user->person?->city,
            'state'            => $user->person?->state,
            'postal_code'      => $user->person?->postal_code,
        ];
    }
}
