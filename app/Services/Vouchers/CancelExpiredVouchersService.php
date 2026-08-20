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

                    $voucher->distributor->increment('available_credit', $voucher->total_debt_amount);
                    $canceledCount++;
                }
            }

            return $canceledCount;
        });
    }
}
