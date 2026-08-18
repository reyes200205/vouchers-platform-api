<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

final class DisburseVoucherService
{
    /**
     * @param  array{transfer_reference: string, authorized_number: string, notes?: string|null}  $data
     */
    public function execute(User $user, Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(static function () use ($user, $voucher, $data): Voucher {
            if ($voucher->status !== VoucherStatus::APROBADO) {
                abort(422, 'El vale debe estar aprobado para poder dispersarse.');
            }

            $voucher->loadMissing('customer');

            if ($voucher->customer->status !== CustomerStatus::ACTIVO || $voucher->customer->verified_at === null) {
                abort(422, 'La cajera debe validar la INE y el comprobante de domicilio antes de feriar el vale.');
            }

            $voucher->update([
                'status' => VoucherStatus::ACTIVO,
                'transfer_reference' => $data['transfer_reference'],
                'authorized_number' => $data['authorized_number'],
                'disbursed_by_user_id' => $user->id,
                'transferred_at' => now(),
                'notes' => $data['notes'] ?? $voucher->notes,
            ]);

            return $voucher;
        });
    }
}