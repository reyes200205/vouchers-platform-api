<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use Illuminate\Console\Command;

final class MarkOverdueVouchersCommand extends Command
{
    protected $signature = 'vouchers:mark-overdue';

    protected $description = 'Marca como morosos los vales cuyo plazo venció y aún tienen saldo.';

    public function handle(): int
    {
        $updated = Voucher::query()
            ->whereIn('status', [VoucherStatus::ACTIVO, VoucherStatus::PAGO_PARCIAL])
            ->where('current_balance', '>', 0)
            ->whereDate('payment_due_date', '<', now()->toDateString())
            ->update(['status' => VoucherStatus::MOROSO]);

        $this->info("Vales marcados como morosos: {$updated}");

        return self::SUCCESS;
    }
}