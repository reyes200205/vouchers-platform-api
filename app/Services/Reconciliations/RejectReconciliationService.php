<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\ReconciliationStatus;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rechaza una conciliación manual que sigue pendiente de la segunda
 * autorización: la cajera propuso emparejar una transacción bancaria con una
 * relación de corte, pero el gerente de sucursal (o general) determina que
 * el emparejamiento no es correcto (relación equivocada, monto que no
 * corresponde, referencia que no coincide, etc.).
 *
 * A diferencia de aprobar (ver VerifyReconciliationService), aquí no hay
 * nada que revertir sobre la relación de corte: verify() es quien la marca
 * PAGADA/PARCIAL y libera crédito/puntos, y el rechazo nunca llegó a
 * tocarla. Se elimina la conciliación y el pago detectado que la cajera
 * había registrado (ambos solo existían como propuesta, nunca se aplicaron
 * a nada) para que la transacción bancaria vuelva a quedar libre y la cajera
 * pueda intentar de nuevo con la relación correcta -- si en vez de esto se
 * dejara la fila marcada como RECHAZADA, el índice único sobre
 * bank_transaction_id la bloquearía para siempre y ManualMatchDepositService
 * seguiría abortando con "la transacción ya fue conciliada".
 *
 * El motivo del rechazo no se persiste aquí (las filas se eliminan, no
 * queda dónde guardarlo) -- el controlador lo manda al log de auditoría
 * junto con los datos de la conciliación antes de llamar a este servicio.
 */
final class RejectReconciliationService
{
    public function execute(User $user, Reconciliation $reconciliation): void
    {
        if ($reconciliation->status !== ReconciliationStatus::PENDIENTE_VERIFICACION) {
            abort(422, 'La conciliación no está pendiente de verificación.');
        }

        if ($reconciliation->reconciled_by_user_id === $user->id) {
            abort(422, 'El rechazo debe realizarlo un usuario distinto al que registró la conciliación.');
        }

        DB::transaction(function () use ($reconciliation): void {
            $payment = $reconciliation->distributorPayment;

            $reconciliation->delete();
            $payment?->delete();
        });
    }
}
