<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorPaymentStatus;
use App\Enums\DistributorStatus;
use App\Enums\PointMovementType;
use App\Enums\VoucherStatus;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
use App\Models\PointMovement;
use App\Models\PointSetting;
use App\Models\Voucher;

/**
 * Se ejecuta cuando una CutoffRelation (deuda de una distribuidora con la
 * sucursal en un corte) queda PAGADA o PARCIAL — vía conciliación automática
 * (AutoMatchDepositsService), verificación manual del gerente
 * (VerifyReconciliationService), o una corrección retroactiva
 * (RetroactiveReconciliationService). Nunca se dispara desde un pago
 * individual de cliente: eso ya no existe como fuente de verdad.
 *
 * Si la relación quedó PAGADA, hace dos cosas, ambas a nivel de la relación
 * (no del cliente):
 *  1. Registra el pago de cada vale detrás de la relación (payments_made/
 *     current_balance/status), una vez por cada item de la relación — un
 *     vale puede tener dos items en el mismo corte (su quincena normal + el
 *     arrastre de una quincena atrasada), y en ese caso liquida dos
 *     quincenas de una vez. El calendario del vale (payment_due_date,
 *     early_payment_start/end_date, installments_billed) NO se toca aquí:
 *     eso ya avanzó al facturarse en GenerateCutoffService, independientemente
 *     de si se pagó.
 *  2. Otorga los puntos a la distribuidora (nunca al cliente), aplicando el
 *     -20% (configurable) si la relación llegó a tener multa por atraso, o el
 *     bono por conciliación anticipada si se resolvió dentro de la ventana.
 *  3. Si la distribuidora estaba MOROSA (bloqueada por 3 cortes consecutivos
 *     sin pagar -- ver MarkOverdueRelationsCommand) y ya no le queda ninguna
 *     otra relación VENCIDA, la reactiva sola (ver
 *     maybeReactivateDistributor): esa es la única forma de quitarle el
 *     bloqueo para pedir vales nuevos.
 *
 * Si la relación quedó PARCIAL (el depósito conciliado no cubre el total
 * adeudado), NINGÚN vale avanza ni se otorgan puntos -- solo se registra,
 * item por item, cuánto de lo adeudado SÍ se cubrió ya (previous_paid_amount)
 * para que GenerateCutoffService arrastre al siguiente corte el remanente
 * real en vez de la deuda original completa otra vez.
 */
final class SettleCutoffRelationService
{
    public function execute(CutoffRelation $relation): void
    {
        // Una relación PARCIAL nunca se liquida (no le toca a ningún vale
        // avanzar ni a la distribuidora ganar puntos por lo que todavía no
        // remitió completo) -- pero SÍ hay que registrar cuánto de lo
        // adeudado ya cubrió, para que el arrastre al siguiente corte
        // (GenerateCutoffService) sea sobre el remanente real y no sobre la
        // deuda original completa otra vez (el bug reportado: "concilié una
        // quincena con adeudo pero me sigue apareciendo como adeudo
        // completo en las quincenas nuevas").
        if ($relation->status === CutoffRelationStatus::PARCIAL) {
            $this->applyPartialPayment($relation);

            return;
        }

        if ($relation->status !== CutoffRelationStatus::PAGADA) {
            return;
        }

        $this->advanceVouchers($relation);
        $this->awardPoints($relation);
        $this->maybeReactivateDistributor($relation);
    }

    /**
     * Una distribuidora que acumuló 3 cortes consecutivos sin pagar queda
     * MOROSA y can_issue_vouchers pasa a false (ver
     * MarkOverdueRelationsCommand) -- ya no puede pedir ni recibir vales
     * nuevos (ver RequestVoucherService/ApproveVoucherService). La única
     * forma de quitarle ese bloqueo es pagando lo que debe: en cuanto esta
     * relación queda PAGADA, si ya no le queda ninguna otra relación VENCIDA
     * (adeudo vencido sin liquidar), se reactiva sola -- no hace falta que
     * un gerente la desbloquee a mano. Una relación PARCIAL no cuenta como
     * "ya pagó" (sigue debiendo el remanente), así que no dispara esto.
     */
    private function maybeReactivateDistributor(CutoffRelation $relation): void
    {
        $distributor = $relation->distributor()->first();

        if ($distributor === null || $distributor->status !== DistributorStatus::MOROSA) {
            return;
        }

        $stillHasOverdueDebt = CutoffRelation::query()
            ->where('distributor_id', $distributor->id)
            ->where('status', CutoffRelationStatus::VENCIDA)
            ->exists();

        if ($stillHasOverdueDebt) {
            return;
        }

        $distributor->update([
            'status' => DistributorStatus::ACTIVA,
            'can_issue_vouchers' => true,
        ]);
    }

    private function advanceVouchers(CutoffRelation $relation): void
    {
        foreach ($relation->items()->get() as $item) {
            $this->advanceVoucherForItem($item);
        }
    }

    /**
     * Aplica a UN item el mismo avance que advanceVouchers() le da a todos
     * los de una relación PAGADA: registra el pago del vale detrás de ese
     * item (payments_made/current_balance/status) y libera su porción de
     * crédito disponible. Público porque GenerateCutoffService también lo
     * usa, item por item, cuando un item de una relación PARCIAL ya quedó
     * cubierto por pagos anteriores (ver applyPartialPayment) y por lo tanto
     * NO debe arrastrarse -- ese vale sí terminó de pagar esa quincena
     * aunque el resto de la relación (otros items) siga con adeudo.
     */
    public function advanceVoucherForItem(CutoffRelationItem $item): void
    {
        if ($item->voucher_id === null) {
            return;
        }

        $voucher = Voucher::query()->find($item->voucher_id);

        if ($voucher === null) {
            return;
        }

        if (in_array($voucher->status, [VoucherStatus::PAGADO, VoucherStatus::LIQUIDADO, VoucherStatus::CANCELADO, VoucherStatus::REVERSADO], true)) {
            return;
        }

        $paid = (float) $item->payment_amount;
        $remaining = round((float) $voucher->current_balance - $paid, 2);
        $paymentsMade = $voucher->payments_made + 1;

        $isSettled = $remaining <= 0.005 || $paymentsMade >= $voucher->total_fortnights;

        // El calendario del vale (payment_due_date, early_payment_start/
        // end_date, installments_billed) YA avanzó cuando se facturó
        // esta quincena en GenerateCutoffService — sin importar si se
        // pagaba a tiempo, atrasada, o se seguía arrastrando. Aquí, al
        // liquidarse, solo se registra que el pago SÍ llegó: cuántas
        // quincenas lleva pagadas la distribuidora y cuánto le queda de
        // saldo.
        $voucher->update([
            'payments_made' => $paymentsMade,
            'current_balance' => max($remaining, 0.0),
            'status' => $isSettled ? VoucherStatus::PAGADO : VoucherStatus::ACTIVO,
        ]);

        // El crédito disponible de la distribuidora se descuenta por el
        // PRINCIPAL del vale al aprobarlo (ver ApproveVoucherService), y se
        // va liberando en la misma proporción: cada quincena que se liquida
        // recupera 1/total_fortnights del PRINCIPAL (nunca lo que se pagó
        // realmente, que trae mezclado interés/seguro/comisión) -- ej. un
        // vale de $15,000 a 8 quincenas libera $1,875 por cada quincena
        // pagada, no la quincena completa que cobró la distribuidora. Antes
        // no se liberaba nada hasta que el vale se terminaba de pagar por
        // completo, dejando el credito disponible de la distribuidora
        // bloqueado de mas durante todo el plazo.
        $principalPerFortnight = round((float) $voucher->amount / $voucher->total_fortnights, 2);

        if ($isSettled) {
            // En la ultima quincena se libera lo que falte para sumar
            // exactamente el principal completo, para no perder ni pasarse
            // por el redondeo acumulado de las quincenas anteriores.
            $alreadyReleased = round($principalPerFortnight * ($paymentsMade - 1), 2);
            $release = round((float) $voucher->amount - $alreadyReleased, 2);
        } else {
            $release = $principalPerFortnight;
        }

        $voucher->distributor()->increment('available_credit', $release);
    }

    /**
     * Reparte lo que la distribuidora ya remitió y se verificó para esta
     * relación (puede venir de más de un depósito a lo largo del tiempo --
     * ManualMatchDepositService permite volver a registrar uno contra una
     * relación que ya quedó PARCIAL) entre sus items. Se recalcula desde
     * cero cada vez (no se va sumando incremento a incremento) para que sea
     * idempotente y se autocorrija sin importar cuántas veces se llame.
     *
     * No todos los pagos son "genéricos": uno puede venir de una corrección
     * retroactiva (RetroactiveReconciliationService) que reasignó el pago al
     * extremo vivo de la cadena de arrastre porque la relación que el
     * usuario seleccionó al conciliar manualmente ya estaba CERRADA (su
     * deuda ya se había arrastrado). En ese caso, reconciliation.
     * original_cutoff_relation_id conserva a qué relación (ya cerrada)
     * correspondía en realidad ese depósito -- y ese pago debe aplicarse
     * SOLO al item de arrastre que viene de esa relación (origin_relation_id),
     * no repartirse sobre lo primero que encuentre. Antes, un depósito
     * pensado para saldar el arrastre de una quincena vieja terminaba
     * cubriendo la quincena normal actual en su lugar (por ser el primer
     * item), dejando el arrastre real -- y su multa -- sin tocar, aunque el
     * usuario sí hubiera conciliado correctamente contra esa relación vieja.
     *
     * Un item que queda totalmente cubierto avanza su vale de una vez (ver
     * advanceVoucherForItem) -- ya no tiene nada pendiente que arrastrar. Un
     * item cubierto solo parcialmente (o nada) dejará como remanente real
     * `line_total_amount - previous_paid_amount`, que es lo que
     * GenerateCutoffService debe arrastrar al siguiente corte -- no el monto
     * original completo otra vez.
     */
    private function applyPartialPayment(CutoffRelation $relation): void
    {
        $payments = $relation->payments()
            ->where('status', DistributorPaymentStatus::RECONCILED)
            ->with('reconciliation')
            ->get();

        $generalPool = 0.0;
        $targetedPools = [];

        foreach ($payments as $payment) {
            $originalRelationId = $payment->reconciliation?->original_cutoff_relation_id;

            if ($originalRelationId === null || $originalRelationId === $relation->id) {
                $generalPool += (float) $payment->amount;
            } else {
                $targetedPools[$originalRelationId] = ($targetedPools[$originalRelationId] ?? 0.0) + (float) $payment->amount;
            }
        }

        $targetedPools = array_map(fn (float $amount): float => round($amount, 2), $targetedPools);

        $items = $relation->items()->orderBy('id')->get();
        $appliedPerItem = [];

        // Primera pasada: cada item de arrastre reclama primero el pool
        // dirigido a SU relación de origen (si hay uno).
        foreach ($items as $item) {
            $itemDue = round((float) $item->line_total_amount, 2);
            $applied = 0.0;

            if ($item->origin_relation_id !== null && ($targetedPools[$item->origin_relation_id] ?? 0.0) > 0.005) {
                $applied = round(min($targetedPools[$item->origin_relation_id], $itemDue), 2);
                $targetedPools[$item->origin_relation_id] = round($targetedPools[$item->origin_relation_id] - $applied, 2);
            }

            $appliedPerItem[$item->id] = $applied;
        }

        // Lo que haya sobrado de un pool dirigido (el item ya estaba cubierto
        // por un pago previo, o ya no hay ningún item con ese origen -- ej.
        // una corrección que llegó de más) se une al pool general en vez de
        // perderse, y se reparte en orden sobre lo que quede pendiente.
        $remainingGeneral = round($generalPool + array_sum($targetedPools), 2);
        $totalStillDue = 0.0;

        foreach ($items as $item) {
            $itemDue = round((float) $item->line_total_amount, 2);
            $applied = $appliedPerItem[$item->id];
            $stillOwed = round($itemDue - $applied, 2);

            if ($stillOwed > 0.005 && $remainingGeneral > 0.005) {
                $fromGeneral = round(min($remainingGeneral, $stillOwed), 2);
                $applied = round($applied + $fromGeneral, 2);
                $remainingGeneral = round($remainingGeneral - $fromGeneral, 2);
            }

            if (round((float) $item->previous_paid_amount, 2) !== $applied) {
                $item->update(['previous_paid_amount' => $applied]);
            }

            $itemRemaining = round($itemDue - $applied, 2);

            if ($itemRemaining <= 0.005) {
                $this->advanceVoucherForItem($item->refresh());
            }

            $totalStillDue += max($itemRemaining, 0.0);
        }

        $relation->update(['total_amount_due' => round($totalStillDue, 2)]);
    }

    private function awardPoints(CutoffRelation $relation): void
    {
        $distributor = $relation->distributor()->with(['category', 'branch.branchSetting'])->first();

        if ($distributor === null) {
            return;
        }

        $amount = (float) $relation->total_payment;

        if ($amount <= 0) {
            return;
        }

        // La fórmula de puntos la configura cada sucursal (branch_settings),
        // igual que sus demás reglas de negocio — no un numero fijo en el
        // código. point_settings (global) solo queda como el default del
        // sistema para cuando una sucursal no configuró su propio valor.
        $branchSetting = $distributor->branch?->branchSetting;

        $divisor = (int) ($branchSetting?->point_divisor_factor
            ?? PointSetting::query()->value('point_divisor_factor')
            ?? 1200);
        $multiplier = (int) ($distributor->category?->points_per_1200
            ?? $branchSetting?->point_multiplier
            ?? PointSetting::query()->value('point_multiplier')
            ?? 3);

        $basePoints = (int) floor($amount / $divisor) * $multiplier;

        if ($basePoints <= 0) {
            return;
        }

        // La fórmula (total / divisor, piso, * multiplicador) es la fórmula de
        // puntos para pagos anticipados — no hay un bono extra aparte, solo se
        // le "quita" un % cuando llega fuera de tiempo. Una relación "fuera de
        // tiempo" es la que en algún momento llegó a vencerse y acumuló multa
        // (MarkOverdueRelationsService); ese es el único lugar donde se decide
        // si un corte llegó tarde, para no repetir esa lógica de fechas en más
        // de un servicio. Esto son puntos, no dinero: point_value_mxn solo se
        // guarda como referencia de a cuánto equivale cada punto al canjearlo.
        $wasLate = ((float) $relation->total_late_fees) > 0;

        if ($wasLate) {
            $penaltyPercentage = (float) ($distributor->category?->late_penalty_percentage
                ?? $branchSetting?->late_penalty_percentage
                ?? PointSetting::query()->value('late_penalty_percentage')
                ?? 20.0);

            $points = (int) floor($basePoints * (1 - $penaltyPercentage / 100));
            $type = PointMovementType::GANADO_PUNTUAL;
            $reason = "Corte conciliado fuera de tiempo (-{$penaltyPercentage}% de puntos).";
        } else {
            $points = $basePoints;
            $type = PointMovementType::GANADO_ANTICIPADO;
            $reason = 'Corte conciliado a tiempo (pago anticipado).';
        }

        if ($points <= 0) {
            return;
        }

        PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'cutoff_id' => $relation->cutoff_id,
            'transaction_type' => $type,
            'points' => $points,
            'point_value_snapshot' => $branchSetting?->point_value_mxn ?? 2.00,
            'reason' => $reason,
            'transaction_date' => now(),
        ]);

        $distributor->increment('current_points', $points);
    }
}
