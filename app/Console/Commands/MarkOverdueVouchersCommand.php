<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * DEPRECADO: el atraso (multa/interés, quita de comisión de la distribuidora,
 * y marcar los vales afectados como MOROSO) ahora se resuelve por completo a
 * nivel de la relación de corte de la distribuidora — ver
 * MarkOverdueRelationsService (comando `cutoffs:mark-overdue`, ya corre a
 * diario). Este comando comparaba el payment_due_date del vale directamente,
 * una fecha independiente de la de su CutoffRelation, lo que duplicaba esa
 * misma decisión con su propio calendario y podía volver a sumarle la multa
 * al vale por separado. Se deja este archivo como stub inerte (ya no está en
 * el schedule, ver routes/console.php) en vez de borrarlo, para no dejar un
 * comando registrable que vuelva a aplicar esa lógica por accidente.
 */
final class MarkOverdueVouchersCommand extends Command
{
    protected $signature = 'vouchers:mark-overdue';

    protected $description = 'DEPRECADO — usa cutoffs:mark-overdue. No hace nada.';

    public function handle(): int
    {
        $this->warn('Este comando está deprecado y no hace nada. El atraso de vales ahora se resuelve con cutoffs:mark-overdue (MarkOverdueRelationsService), a nivel de la relación de corte de la distribuidora.');

        return self::SUCCESS;
    }
}
