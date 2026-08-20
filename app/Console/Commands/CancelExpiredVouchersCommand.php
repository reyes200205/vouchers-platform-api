<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Vouchers\CancelExpiredVouchersService;
use Illuminate\Console\Command;

final class CancelExpiredVouchersCommand extends Command
{
    protected $signature = 'vouchers:cancel-expired';

    protected $description = 'Cancela vales aprobados que han expirado según la configuración de la sucursal y devuelve el crédito a la distribuidora';

    public function handle(CancelExpiredVouchersService $service): int
    {
        $this->info('Iniciando proceso de expiración de vales...');
        
        $count = $service->execute();

        $this->info("Proceso terminado. Se cancelaron {$count} vales por vencimiento.");

        return self::SUCCESS;
    }
}
