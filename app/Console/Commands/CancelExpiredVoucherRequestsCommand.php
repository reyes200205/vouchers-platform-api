<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Vouchers\CancelExpiredVoucherRequestsService;
use Illuminate\Console\Command;

final class CancelExpiredVoucherRequestsCommand extends Command
{
    protected $signature = 'vouchers:cancel-expired-requests';

    protected $description = 'Cancela solicitudes de vale pendientes que vencieron sin ser aprobadas y devuelve el crédito apartado a la distribuidora';

    public function handle(CancelExpiredVoucherRequestsService $service): int
    {
        $this->info('Iniciando proceso de expiración de solicitudes de vale...');

        $count = $service->execute();

        $this->info("Proceso terminado. Se cancelaron {$count} solicitudes por vencimiento.");

        return self::SUCCESS;
    }
}
