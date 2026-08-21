<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorPaymentStatus;
use App\Enums\PointMovementType;
use App\Enums\ReconciliationStatus;
use App\Enums\VoucherStatus;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
use App\Models\PointMovement;
use App\Models\PointSetting;
use App\Models\Reconciliation;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Cutoffs\SettleCutoffRelationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrige retroactivamente una relación de corte (y, si su deuda ya se
 * arrastró, toda la cadena de relaciones que nacieron de ella) cuando la
 * cajera nunca registró un pago que en realidad SÍ llegó a tiempo -- según la
 * fecha real del depósito bancario (bank_transactions.transaction_date,
 * guardada en distributor_payments.payment_date al emparejar), comparada
 * contra la ventana "a tiempo" de la relación original
 * (early_payment_start_date - early_payment_end_date, el mismo periodo del
 * corte donde esa relación se generó).
 *
 * Se dispara desde VerifyReconciliationService cuando la conciliación viene
 * marcada is_retroactive_correction = true (ver ManualMatchDepositService,
 * que decide si la relación elegida amerita esta lógica en vez del flujo
 * normal). VerifyReconciliationService ya validó ahí que la conciliación
 * sigue pendiente y que la aprueba un usuario distinto al que la registró --
 * este servicio no repite esas validaciones.
 *
 * Qué hace, en orden:
 *  1. Encuentra la relación "viva" (el extremo de la cadena de arrastre que
 *     nació de la relación seleccionada): mientras una relación esté CERRADA
 *     (su deuda ya se arrastró a la siguiente), sigue previous_relation_id
 *     hacia adelante -- GenerateCutoffService enlaza ese campo en cada
 *     relación que genera, se esté arrastrando o no, así que el recorrido
 *     siempre encuentra el siguiente eslabón real.
 *  2. Si la fecha del depósito cae dentro de la ventana "a tiempo" de la
 *     relación original, recorre esa misma relación y cada relación
 *     siguiente en la cadena (identificando en cada una los items que
 *     arrastran la deuda de la original, vía origin_relation_id) y les
 *     quita la multa / les regresa la comisión -- recalculada con la
 *     fórmula original del vale (distributor_profit_amount /
 *     total_fortnights), no con lo que haya quedado guardado, porque
 *     MarkOverdueRelationsService pone la comisión en 0 sin conservar
 *     cuánto era.
 *  3. Si la relación viva ya estaba PAGADA (se liquidó con la multa
 *     incluida), no se vuelve a facturar el vale ni a liberar crédito otra
 *     vez (SettleCutoffRelationService::advanceVouchers ya lo hizo la
 *     primera vez, y nunca dependió de la multa) -- solo se corrige la
 *     diferencia de puntos, que sí dependía de si hubo multa, agregando un
 *     movimiento de ajuste positivo por la diferencia.
 *  4. Si la relación viva todavía no se liquidaba (GENERADA/PARCIAL/
 *     VENCIDA), se marca PAGADA/PARCIAL igual que VerifyReconciliationService
 *     y se llama a SettleCutoffRelationService normalmente -- primera vez,
 *     sin duplicar nada, ya con los totales corregidos.
 *  5. Si la fecha NO cae en la ventana a tiempo, no se quita ninguna multa
 *     (el atraso fue real): esto entonces solo sirve para poder registrar el
 *     pago de una relación CERRADA que de otra forma sería inalcanzable.
 */
final class RetroactiveReconciliationService
{
    public function __construct(
        private readonly SettleCutoffRelationService $settleCutoffRelationService,
    ) {}

    public function execute(User $user, Reconciliation $reconciliation): Reconciliation
    {
        return DB::transaction(function () use ($user, $reconciliation): Reconciliation {
            $payment = $reconciliation->distributorPayment;
            $original = CutoffRelation::query()->findOrFail(
                $reconciliation->original_cutoff_relation_id ?? $payment->cutoff_relation_id
            );

            $tip = $this->findLiveTip($original);
            $onTime = $this->isWithinOnTimeWindow($original, $payment->payment_date);

            $waivedTotal = $onTime ? $this->waiveLateFeesAcrossChain($original, $tip) : 0.0;

            $tip = $tip->refresh();
            $wasAlreadySettled = $tip->status === CutoffRelationStatus::PAGADA;

            $difference = round((float) $payment->amount - (float) $tip->total_amount_due, 2);

            $payment->update([
                'status' => DistributorPaymentStatus::RECONCILED,
                'cutoff_relation_id' => $tip->id,
            ]);

            if (! $wasAlreadySettled) {
                $tip->update([
                    'status' => abs($difference) <= 0.01
                        ? CutoffRelationStatus::PAGADA
                        : CutoffRelationStatus::PARCIAL,
                ]);
            }

            $reconciliation->update([
                'reconciled_amount' => $payment->amount,
                'amount_difference' => $difference,
                'status' => abs($difference) <= 0.01
                    ? ReconciliationStatus::CONCILIADA
                    : ReconciliationStatus::CON_DIFERENCIA,
                'verified_by_user_id' => $user->id,
                'verified_at' => now(),
                'waived_late_fees_total' => round($waivedTotal, 2),
            ]);

            if ($wasAlreadySettled) {
                if ($onTime) {
                    $this->correctAlreadyAwardedPoints($tip);
                }
            } else {
                $this->settleCutoffRelationService->execute($tip->refresh());
            }

            return $reconciliation->refresh();
        });
    }

    private function findLiveTip(CutoffRelation $relation): CutoffRelation
    {
        $current = $relation;

        while ($current->status === CutoffRelationStatus::CERRADA) {
            $next = CutoffRelation::query()->where('previous_relation_id', $current->id)->first();

            if ($next === null) {
                abort(422, 'No se encontró la relación que arrastró la deuda de esta relación cerrada.');
            }

            $current = $next;
        }

        return $current;
    }

    private function isWithinOnTimeWindow(CutoffRelation $original, ?Carbon $paymentDate): bool
    {
        if ($paymentDate === null || $original->early_payment_start_date === null || $original->early_payment_end_date === null) {
            return false;
        }

        $date = Carbon::parse($paymentDate)->startOfDay();
        $start = Carbon::parse($original->early_payment_start_date)->startOfDay();
        $end = Carbon::parse($original->early_payment_end_date)->endOfDay();

        return $date->between($start, $end);
    }

    /**
     * @return float el total de multa que se quitó a lo largo de toda la cadena
     */
    private function waiveLateFeesAcrossChain(CutoffRelation $original, CutoffRelation $tip): float
    {
        // origin_relation_id se "aplana" hacia la primera relación sin pagar de
        // la cadena (ver GenerateCutoffService), así que normalmente basta con
        // buscar $original->id en las relaciones siguientes. Pero si $original
        // en sí ya traía items arrastrados de una relación todavía más vieja
        // (una "cadena dentro de la cadena"), esos items conservan el id de esa
        // relación más vieja, no el de $original -- por eso también se incluyen
        // los origin_relation_id que ya traían los items de $original, para no
        // dejar fuera esa deuda al corregir las relaciones siguientes.
        $originIds = $original->items()
            ->whereNotNull('origin_relation_id')
            ->pluck('origin_relation_id')
            ->push($original->id)
            ->unique()
            ->values()
            ->all();

        $waived = 0.0;
        $current = $original;

        while (true) {
            $items = $current->id === $original->id
                ? $current->items()->get()
                : $current->items()->whereIn('origin_relation_id', $originIds)->get();

            foreach ($items as $item) {
                if ((float) $item->late_fee_amount <= 0 && ! $item->is_late_payment) {
                    continue;
                }

                $waived += (float) $item->late_fee_amount;
                $this->correctItem($item);
            }

            $this->recalculateRelationTotals($current);

            if ($current->id === $tip->id) {
                break;
            }

            $next = CutoffRelation::query()->where('previous_relation_id', $current->id)->first();

            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return $waived;
    }

    private function correctItem(CutoffRelationItem $item): void
    {
        $voucher = $item->voucher_id !== null ? Voucher::query()->find($item->voucher_id) : null;

        if ($voucher !== null && $voucher->total_fortnights > 0) {
            $commission = round((float) $voucher->distributor_profit_amount / $voucher->total_fortnights, 2);
            $grossPerFortnight = ((float) $voucher->total_debt_amount) / $voucher->total_fortnights;
            $correctedLineTotal = floor($grossPerFortnight - $commission);
        } else {
            $commission = 0.0;
            $correctedLineTotal = round((float) $item->payment_amount - (float) $item->late_fee_amount, 2);
        }

        $update = [
            'is_late_payment' => false,
            'commission_amount' => $commission,
            'late_fee_amount' => 0.00,
            'line_total_amount' => $correctedLineTotal,
        ];

        // Un item de arrastre siempre trae payment_amount === line_total_amount
        // (ver GenerateCutoffService): se corrige igual para que ambas columnas
        // sigan reflejando el mismo monto, ya sin la multa. El item normal
        // (sin origen) conserva su payment_amount -- ese siempre es la
        // quincena completa que le cobró al cliente, independiente de la
        // multa/comisión.
        if ($item->origin_relation_id !== null) {
            $update['payment_amount'] = $correctedLineTotal;
        }

        $item->update($update);

        // Simplificación: si este item deja de ser tardío, el vale deja de
        // estar en mora por esta causa. Si en realidad debiera otra quincena
        // atrasada aparte, MarkOverdueRelationsService/SettleCutoffRelationService
        // lo volverán a marcar en su momento -- aquí no se intenta rastrear si
        // hay otra causa simultánea de mora sobre el mismo vale.
        if ($voucher !== null && $voucher->status === VoucherStatus::MOROSO) {
            $voucher->update(['status' => VoucherStatus::ACTIVO]);
        }
    }

    private function recalculateRelationTotals(CutoffRelation $relation): void
    {
        $relation->update([
            'total_late_fees' => round((float) $relation->items()->sum('late_fee_amount'), 2),
            'total_commission' => round((float) $relation->items()->sum('commission_amount'), 2),
            'total_amount_due' => round((float) $relation->items()->sum('line_total_amount'), 2),
        ]);
    }

    /**
     * La relación viva ya se había liquidado (PAGADA) con la multa incluida:
     * SettleCutoffRelationService::awardPoints ya le otorgó los puntos con el
     * -20% (GANADO_PUNTUAL). Ahora que se corrigió y ya no tiene multa, se le
     * completa la diferencia contra los puntos completos (GANADO_ANTICIPADO)
     * mediante un movimiento nuevo -- nunca se edita ni se borra el movimiento
     * original, para no perder el rastro de auditoría.
     */
    private function correctAlreadyAwardedPoints(CutoffRelation $relation): void
    {
        $distributor = $relation->distributor()->with(['category', 'branch.branchSetting'])->first();

        if ($distributor === null) {
            return;
        }

        $oldMovement = PointMovement::query()
            ->where('distributor_id', $distributor->id)
            ->where('cutoff_id', $relation->cutoff_id)
            ->whereIn('transaction_type', [PointMovementType::GANADO_PUNTUAL, PointMovementType::GANADO_ANTICIPADO])
            ->latest('id')
            ->first();

        if ($oldMovement === null || $oldMovement->transaction_type !== PointMovementType::GANADO_PUNTUAL) {
            // Ya se había otorgado como "a tiempo" (o nunca se otorgó nada
            // porque el monto era 0) -- nada que corregir.
            return;
        }

        $amount = (float) $relation->total_payment;

        if ($amount <= 0) {
            return;
        }

        $branchSetting = $distributor->branch?->branchSetting;

        $divisor = (int) ($branchSetting?->point_divisor_factor
            ?? PointSetting::query()->value('point_divisor_factor')
            ?? 1200);
        $multiplier = (int) ($distributor->category?->points_per_1200
            ?? $branchSetting?->point_multiplier
            ?? PointSetting::query()->value('point_multiplier')
            ?? 3);

        $correctPoints = (int) floor($amount / $divisor) * $multiplier;
        $oldPoints = (int) $oldMovement->points;
        $delta = $correctPoints - $oldPoints;

        if ($delta <= 0) {
            return;
        }

        PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'cutoff_id' => $relation->cutoff_id,
            'transaction_type' => PointMovementType::AJUSTE_MANUAL,
            'points' => $delta,
            'point_value_snapshot' => $branchSetting?->point_value_mxn ?? 2.00,
            'reason' => "Corrección retroactiva: el corte se había conciliado con multa por error de la cajera; el pago real llegó a tiempo (+{$delta} pts).",
            'transaction_date' => now(),
        ]);

        $distributor->increment('current_points', $delta);
    }
}
