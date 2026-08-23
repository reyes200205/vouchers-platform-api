<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorPaymentStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\Cutoffs\SettleCutoffRelationService;
use Illuminate\Support\Facades\DB;

final class VerifyReconciliationService
{
    public function __construct(
        private readonly SettleCutoffRelationService $settleCutoffRelationService,
        private readonly RetroactiveReconciliationService $retroactiveReconciliationService,
    ) {
    }

    public function execute(User $user, Reconciliation $reconciliation): Reconciliation
    {
        if ($reconciliation->status !== ReconciliationStatus::PENDIENTE_VERIFICACION) {
            abort(422, 'La conciliación no está pendiente de verificación.');
        }

        if ($reconciliation->reconciled_by_user_id === $user->id) {
            abort(422, 'La segunda autorización debe realizarla un usuario distinto al que registró la conciliación.');
        }

        // Se registró contra una relación VENCIDA/CERRADA (o PAGADA con multa):
        // puede que el pago real sí haya llegado a tiempo y la multa se haya
        // aplicado por error de la cajera. RetroactiveReconciliationService
        // decide, según la fecha real del depósito, si corresponde quitarla
        // (y a lo largo de toda la cadena de arrastre) antes de liquidar --
        // ver ManualMatchDepositService, que es quien pone esta bandera.
        if ($reconciliation->is_retroactive_correction) {
            return $this->retroactiveReconciliationService->execute($user, $reconciliation);
        }

        return DB::transaction(function () use ($user, $reconciliation): Reconciliation {
            $payment = $reconciliation->distributorPayment;
            $relation = $payment->cutoffRelation;

            $payment->update(['status' => DistributorPaymentStatus::RECONCILED]);

            $reconciliation->update([
                'status' => abs((float) $reconciliation->amount_difference) <= 0.01
                    ? ReconciliationStatus::CONCILIADA
                    : ReconciliationStatus::CON_DIFERENCIA,
                'verified_by_user_id' => $user->id,
                'verified_at' => now(),
            ]);

            $relation->update([
                'status' => abs((float) $reconciliation->amount_difference) <= 0.01
                    ? CutoffRelationStatus::PAGADA
                    : CutoffRelationStatus::PARCIAL,
            ]);

            // El crédito disponible ya NO se libera aquí por el monto pagado: se
            // descontó por el principal del vale al aprobarlo y se libera completo
            // hasta que el vale se termina de pagar (ver SettleCutoffRelationService,
            // que es quien de verdad decide si un vale quedó liquidado).
            // Solo hace algo si la relación quedó PAGADA: avanza los vales detrás
            // de ella y le otorga los puntos a la distribuidora (nunca al cliente).
            $this->settleCutoffRelationService->execute($relation->refresh());

            return $reconciliation->refresh();
        });
    }
}