<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Cutoffs\CutoffPeriodCalculator;
use Illuminate\Support\Facades\DB;

final class DisburseVoucherService
{
    /**
     * @param  array{transfer_reference: string, authorized_number: string, notes?: string|null}  $data
     */
    public function execute(User $user, Voucher $voucher, array $data, CutoffPeriodCalculator $periods = new CutoffPeriodCalculator()): Voucher
    {
        // Los dias de la quincena los define la sucursal (branch_settings), no el
        // codigo: payment_frequency_days = cada cuantos dias corre una quincena
        // (para calcular la ventana de "pago anticipado"). Los defaults (15 y 14)
        // solo aplican si la sucursal nunca configuro nada.
        $branchSetting = BranchSetting::query()
            ->firstOrCreate(['branch_id' => $voucher->branch_id])
            ->refresh();

        $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
        $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);

        // La primera quincena de un vale recien dispersado SIEMPRE cae en el
        // periodo de corte que sigue al periodo donde se dispersa (nunca en el
        // periodo actual, sin importar que tan temprano en el periodo se pida):
        // el cliente apenas recibio el vale, no le puede tocar pagar en unos
        // dias solo porque el periodo actual ya casi cierra. Ver
        // CutoffPeriodCalculator y branch_settings.cutoff_day (1-15/16-31 por
        // default si la sucursal no configuro un dia de corte distinto).
        $dueDate = $periods->nextPeriodEnd(now(), $branchSetting->cutoff_day);

        return DB::transaction(static function () use ($user, $voucher, $data, $dueDays, $frequencyDays, $dueDate): Voucher {
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
                'payment_due_date' => $dueDate->toDateString(),
                'early_payment_start_date' => $dueDate->copy()->subDays($dueDays - 1)->toDateString(),
                'early_payment_end_date' => $dueDate->copy()->subDays($dueDays - $frequencyDays)->toDateString(),
                'notes' => $data['notes'] ?? $voucher->notes,
            ]);

            return $voucher;
        });
    }
}