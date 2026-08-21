<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
use App\Models\Distributor;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera los cortes de pago por distribuidora.
 *
 * Los clientes le pagan a la distribuidora directamente (fuera del sistema);
 * eso es asunto de la distribuidora. Lo que este servicio calcula es cuánto le
 * corresponde COBRAR a la sucursal a cada distribuidora en este periodo: la
 * suma de las quincenas programadas (fortnightly_payment_amount) de sus vales
 * activos cuyo payment_due_date cae dentro del periodo del corte, más
 * cualquier saldo arrastrado de un corte anterior sin pagar (carryover).
 *
 * Ya NO se agrupan CustomerPayment (no se van a capturar pagos individuales de
 * clientes). Si la distribuidora paga o no, y si su pago llega a tiempo o
 * atrasado, se determina DESPUÉS de generado este corte: al conciliarse
 * (SettleCutoffRelationService, vía AutoMatchDepositsService /
 * VerifyReconciliationService) o al vencerse sin pagar
 * (MarkOverdueRelationsService). Por eso aquí ningún item se marca
 * is_late_payment ni lleva multa: eso todavía no se sabe.
 */
final class GenerateCutoffService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Branch $branch, array $data): Cutoff
    {
        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($data['period_end'])->endOfDay();

        if ($periodEnd->lt($periodStart)) {
            abort(422, 'El periodo final no puede ser anterior al periodo inicial.');
        }

        return DB::transaction(function () use ($branch, $periodStart, $periodEnd): Cutoff {
            $cutoff = Cutoff::query()->create([
                'branch_id' => $branch->id,
                'cutoff_type' => CutoffType::PAGOS,
                'base_day_of_month' => $periodEnd->day,
                'base_time' => $periodEnd->format('H:i:s'),
                'period_start' => $periodStart->toDateString(),
                'scheduled_at' => $periodEnd,
                'executed_at' => now(),
                'status' => CutoffStatus::EJECUTADO,
                'config_snapshot_json' => json_encode([
                    'voucher_amount_step' => $branch->branchSetting?->voucher_amount_step,
                    'pre_vale_max_percentage' => $branch->branchSetting?->pre_vale_max_percentage,
                    'pre_vale_tolerance_amount' => $branch->branchSetting?->pre_vale_tolerance_amount,
                    'point_value_mxn' => $branch->branchSetting?->point_value_mxn,
                ]),
            ]);

            $distributors = Distributor::query()
                ->where('branch_id', $branch->id)
                ->whereNull('deactivated_at')
                ->orderBy('id')
                ->get();

            foreach ($distributors as $distributor) {
                $this->generateRelation($cutoff, $distributor, $periodStart, $periodEnd);
            }

            return $cutoff->refresh();
        });
    }

    /**
     * Publico porque ReprocessCutoffService reutiliza esta misma logica para
     * generar la relacion de una distribuidora que todavia no tiene una en un
     * corte ya existente (sin crear un Cutoff duplicado).
     */
    public function generateRelation(Cutoff $cutoff, Distributor $distributor, Carbon $periodStart, Carbon $periodEnd): void
    {
        $previousRelation = CutoffRelation::query()
            ->where('distributor_id', $distributor->id)
            ->whereIn('status', [CutoffRelationStatus::GENERADA, CutoffRelationStatus::PARCIAL, CutoffRelationStatus::VENCIDA])
            ->latest('id')
            ->first();

        // Vales de la distribuidora cuya proxima quincena programada vence dentro
        // de este periodo. MOROSO se incluye a proposito: un vale que ya estaba
        // atrasado (su corte anterior se vencio) sigue generando su quincena
        // normal en el siguiente corte, aparte del arrastre de la atrasada
        // (carryover, abajo) — asi como se ve en el ejemplo de la pizarra.
        $vouchers = Voucher::query()
            ->where('distributor_id', $distributor->id)
            ->whereIn('status', [VoucherStatus::ACTIVO, VoucherStatus::PAGO_PARCIAL, VoucherStatus::MOROSO])
            ->whereBetween('payment_due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('id')
            ->get();

        if ($previousRelation === null && $vouchers->isEmpty()) {
            return;
        }

        // Los dias limite para que la distribuidora liquide el corte los define
        // la sucursal (branch_settings.payment_due_days/payment_frequency_days),
        // igual que para las quincenas de cada vale — nunca un numero fijo aqui.
        $dueDays = (int) ($cutoff->branch->branchSetting?->payment_due_days ?? 15);
        $frequencyDays = (int) ($cutoff->branch->branchSetting?->payment_frequency_days ?? 14);

        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'previous_relation_id' => $previousRelation?->id,
            'relation_number' => 'REL-'.$cutoff->id.'-'.$distributor->id,
            'payment_reference' => 'REF-'.mb_strtoupper(mb_substr(md5(uniqid((string) $distributor->id, true)), 0, 10)),
            'payment_due_date' => $periodEnd->copy()->addDays($dueDays)->toDateString(),
            'early_payment_start_date' => $periodEnd->copy()->addDay()->toDateString(),
            'early_payment_end_date' => $periodEnd->copy()->addDays($frequencyDays)->toDateString(),
            'credit_limit_snapshot' => $distributor->credit_limit,
            'available_credit_snapshot' => $distributor->available_credit,
            'points_snapshot' => $distributor->current_points,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now(),
        ]);

        $totalPayment = 0.0;
        $totalCommission = 0.0;

        foreach ($vouchers as $voucher) {
            $paymentAmount = round((float) $voucher->fortnightly_payment_amount, 2);
            $commission = $this->calculateDistributorCommission($voucher, $paymentAmount);

            CutoffRelationItem::query()->create([
                'cutoff_relation_id' => $relation->id,
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'product_name_snapshot' => $voucher->financialProduct?->name ?? 'Producto',
                'payments_made' => $voucher->payments_made,
                'total_payments' => $voucher->total_fortnights,
                'is_late_payment' => false,
                'installment_number' => $voucher->payments_made + 1,
                'accumulated_late_installments' => 0,
                // payment_amount es la quincena COMPLETA que la distribuidora ya le
                // cobró al cliente (incluye su comisión de categoría — ver
                // FinancialCalculationService, ya no se descuenta ahí). Aquí, en el
                // corte de relación, sí se descuenta: la distribuidora no le va a
                // pagar su propia comisión a la sucursal, se la queda como
                // ganancia. Por eso line_total_amount = payment_amount - commission.
                // Si no paga a tiempo, MarkOverdueRelationsService le suma de vuelta
                // esa comisión (ya no gana nada) más la multa.
                'commission_amount' => $commission,
                'payment_amount' => $paymentAmount,
                'late_fee_amount' => 0.00,
                'line_total_amount' => round($paymentAmount - $commission, 2),
            ]);

            $totalPayment += $paymentAmount;
            $totalCommission += $commission;
        }

        $carryover = 0.0;

        if ($previousRelation !== null) {
            $unpaidItems = $previousRelation->items()->get();

            foreach ($unpaidItems as $item) {
                $carryover += (float) $item->line_total_amount;

                CutoffRelationItem::query()->create([
                    'cutoff_relation_id' => $relation->id,
                    'voucher_id' => $item->voucher_id,
                    'customer_id' => $item->customer_id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'payments_made' => $item->payments_made,
                    'total_payments' => $item->total_payments,
                    'is_late_payment' => false,
                    'installment_number' => $item->installment_number,
                    'accumulated_late_installments' => $item->accumulated_late_installments,
                    'commission_amount' => 0.00,
                    'payment_amount' => $item->line_total_amount,
                    'late_fee_amount' => 0.00,
                    'line_total_amount' => $item->line_total_amount,
                    'previous_paid_amount' => 0.00,
                    'origin_cutoff_id' => $item->origin_cutoff_id ?? $previousRelation->cutoff_id,
                    'origin_relation_id' => $item->origin_relation_id ?? $previousRelation->id,
                ]);
            }

            $previousRelation->update([
                'status' => CutoffRelationStatus::CERRADA,
                'closed_by_carryover_at' => now(),
            ]);
        }

        $relation->update([
            'total_payment' => round($totalPayment, 2),
            'total_commission' => round($totalCommission, 2),
            'total_late_fees' => 0.00,
            'total_carryover_received' => round($carryover, 2),
            // Lo que la distribuidora debe remitir: la suma de sus quincenas
            // completas menos su comisión total (se la queda) más el arrastre de
            // periodos anteriores sin pagar (que ya viene neto, ver abajo).
            'total_amount_due' => round($totalPayment - $totalCommission + $carryover, 2),
        ]);
    }

    /**
     * Utilidad de la distribuidora sobre un pago del corte.
     *
     * La utilidad total del vale (`distributor_profit_amount`) se cotiza sobre el
     * PRINCIPAL, no sobre el total a pagar (que ya incluye comisión de apertura,
     * seguro e interés) — ver `FinancialCalculationService::calculateVoucherSnapshot()`.
     * Por eso aquí no se vuelve a multiplicar `distributor_profit_percentage_snapshot`
     * contra el pago del periodo (eso duplicaría el % sobre conceptos que no
     * generan utilidad para la distribuidora e infla lo que se queda). En vez de
     * eso, se reparte la utilidad total proporcionalmente a qué fracción de la
     * deuda total representa este pago: si el pago es exactamente una quincena,
     * el resultado es idéntico a `distributor_profit_amount / total_fortnights`.
     */
    private function calculateDistributorCommission(Voucher $voucher, float $paymentAmount): float
    {
        $totalDebt = (float) $voucher->total_debt_amount;
        $profitTotal = (float) $voucher->distributor_profit_amount;

        if ($totalDebt <= 0 || $profitTotal <= 0) {
            return 0.0;
        }

        return round($profitTotal * ($paymentAmount / $totalDebt), 2);
    }
}
