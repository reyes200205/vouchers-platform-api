<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

final class CancelExpiredVouchersService
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            $vouchers = Voucher::query()
                ->where('status', VoucherStatus::APROBADO)
                ->with(['branch.setting', 'distributor'])
                ->get();

            $canceledCount = 0;

            foreach ($vouchers as $voucher) {
                $setting = $voucher->branch?->setting;
                if (! $setting || $setting->voucher_expiration_days === null) {
                    continue;
                }

                $expirationDate = $voucher->issued_at?->copy()->addDays((int) $setting->voucher_expiration_days);
                if ($expirationDate && now()->greaterThan($expirationDate)) {
                    $voucher->update([
                        'status' => VoucherStatus::CANCELADO,
                        'is_canceled' => true,
                        'canceled_at' => now(),
                        'notes' => trim(($voucher->notes ?? '') . ' [Cancelado automáticamente por vencimiento]'),
                    ]);

                    // available_credit mide CAPITAL (principal) prestado, no el total a
                    // cobrar (que ya trae intereses, seguro y comisión encima) -- ver
                    // ApproveVoucherService, que descuenta solo el principal al aprobar.
                    // Devolver total_debt_amount aquí inflaba el crédito disponible de
                    // la distribuidora por encima de lo que en realidad se le había
                    // descontado.
                    $voucher->distributor->increment('available_credit', $voucher->amount);
                    $canceledCount++;
                }
            }

            return $canceledCount;
        });
    }
}
