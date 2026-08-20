<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
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
        // Los dias de la quincena los define la sucursal (branch_settings), no el
        // codigo: payment_due_days = a cuantos dias vence el pago desde hoy;
        // payment_frequency_days = hasta que dia dentro de ese periodo el pago
        // sigue contando como "anticipado" para el bono de puntos. Los defaults
        // (15 y 14) solo aplican si la sucursal nunca configuro nada.
        $branchSetting = BranchSetting::query()
            ->firstOrCreate(['branch_id' => $voucher->branch_id])
            ->refresh();

        $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
        $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);

        return DB::transaction(static function () use ($user, $voucher, $data, $dueDays, $frequencyDays): Voucher {
            if ($voucher->status !== VoucherStatus::APROBADO) {
                abort(422, 'El vale debe estar aprobado para poder dispersarse.');
            }

            $customer = $voucher->loadMissing('customer')->customer;

            if ($customer->status !== CustomerStatus::ACTIVO || $customer->verified_at === null) {
                abort(422, 'El cliente debe ser verificado por la cajera antes de poder recibir el vale.');
            }

            $voucher->update([
                'status' => VoucherStatus::ACTIVO,
                'transfer_reference' => $data['transfer_reference'],
                'authorized_number' => $data['authorized_number'],
                'disbursed_by_user_id' => $user->id,
                'transferred_at' => now(),
                'payment_due_date' => now()->addDays($dueDays)->toDateString(),
                'early_payment_start_date' => now()->addDay()->toDateString(),
                'early_payment_end_date' => now()->addDays($frequencyDays)->toDateString(),
                'notes' => $data['notes'] ?? $voucher->notes,
            ]);

            return $voucher;
        });
    }
}