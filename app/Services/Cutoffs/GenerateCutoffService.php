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
use Illuminate\Database\QueryException;
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
    public function __construct(
        private readonly SettleCutoffRelationService $settleCutoffRelationService,
    ) {}

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

        // Ademas de la regla de periodos consecutivos (abajo, que ya de por si
        // hace imposible volver a mandar un periodo repetido en el flujo
        // normal), esta checa explicitamente el caso puntual que se reporto:
        // que no se pueda crear dos veces un corte para el mismo rango de
        // fechas en la misma sucursal -- por ejemplo un doble clic en
        // "Generar corte" o un reintento de red. El indice unico en la
        // migracion (branch_id + period_start) es el respaldo real contra la
        // condicion de carrera (dos solicitudes casi simultaneas podrian
        // pasar ambas este chequeo antes de que ninguna termine de guardar);
        // este chequeo aqui solo existe para dar un mensaje claro en el caso
        // normal, no de carrera.
        $duplicate = Cutoff::query()
            ->where('branch_id', $branch->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('scheduled_at', $periodEnd->toDateString())
            ->exists();

        if ($duplicate) {
            abort(422, 'Ya existe un corte para este mismo periodo en esta sucursal.');
        }

        // Los periodos de una sucursal tienen que ser consecutivos, sin huecos ni
        // traslapes: si se salta un periodo (ej. genera directo el corte de
        // "dentro de dos quincenas" sin generar antes el de la quincena
        // intermedia), el arrastre de un corte vencido termina pegado al
        // siguiente corte que sí se generó -- el que sea, sin importar que tan
        // lejos esté -- y tanto el adeudo como el atraso aparecen ahí, en vez de
        // en el periodo que en realidad seguía. Forzar periodos consecutivos
        // evita esa clase de bug de raíz.
        $lastCutoff = Cutoff::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('scheduled_at')
            ->first();

        if ($lastCutoff !== null) {
            $lastPeriodEnd = Carbon::parse($lastCutoff->scheduled_at)->startOfDay();
            $expectedStart = $lastPeriodEnd->copy()->addDay();

            if (! $periodStart->isSameDay($expectedStart)) {
                abort(422, sprintf(
                    'El periodo debe iniciar el %s, justo el día después de que terminó el corte anterior (%s). No se pueden saltar ni traslapar periodos.',
                    $expectedStart->toDateString(),
                    $lastPeriodEnd->toDateString(),
                ));
            }
        }

        try {
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
        } catch (QueryException $e) {
            // Respaldo del indice unico (branch_id + period_start, ver
            // migracion) para la condicion de carrera que el chequeo de
            // arriba no alcanza a cubrir por si solo: dos solicitudes casi
            // simultaneas para el mismo periodo. Codigo 23000 = violacion de
            // restriccion unica/integridad, tanto en MySQL como en SQLite.
            if ($e->getCode() === '23000') {
                abort(422, 'Ya existe un corte para este mismo periodo en esta sucursal.');
            }

            throw $e;
        }
    }

    /**
     * Publico porque ReprocessCutoffService reutiliza esta misma logica para
     * generar la relacion de una distribuidora que todavia no tiene una en un
     * corte ya existente (sin crear un Cutoff duplicado).
     */
    public function generateRelation(Cutoff $cutoff, Distributor $distributor, Carbon $periodStart, Carbon $periodEnd): void
    {
        // Si esta distribuidora ya tiene una relacion en ESTE corte (porque ya
        // se genero antes, o porque este mismo metodo ya la creo en una
        // llamada anterior dentro del mismo reprocess), no se crea otra -- se
        // completa la que ya existe con los vales nuevos que todavia no
        // esten ahi. Esto es lo que permite que, si a una distribuidora que
        // ya tiene relacion en el corte abierto le otorgan un vale nuevo (a
        // ese mismo cliente o a otro), ese vale aparezca al reprocesar el
        // corte en vez de quedar invisible hasta el siguiente periodo.
        $relation = CutoffRelation::query()
            ->where('cutoff_id', $cutoff->id)
            ->where('distributor_id', $distributor->id)
            ->first();

        // El arrastre de la relacion anterior (si la hay) solo se busca y se
        // procesa la PRIMERA vez que se crea la relacion de esta
        // distribuidora en este corte -- si ya existia y solo se le estan
        // agregando vales nuevos, el arrastre ya se resolvio esa primera vez
        // y no se vuelve a tocar.
        $previousRelation = null;

        if ($relation === null) {
            $previousRelation = CutoffRelation::query()
                ->where('distributor_id', $distributor->id)
                ->whereIn('status', [CutoffRelationStatus::GENERADA, CutoffRelationStatus::PARCIAL, CutoffRelationStatus::VENCIDA])
                ->latest('id')
                ->first();
        }

        // Vales que ya son item de esta relacion (si ya existia): no se
        // vuelven a agregar aunque su payment_due_date siga cayendo en el
        // periodo.
        $alreadyIncludedVoucherIds = $relation !== null
            ? $relation->items()->whereNotNull('voucher_id')->pluck('voucher_id')->all()
            : [];

        // Vales de la distribuidora cuya proxima quincena programada vence dentro
        // de este periodo. MOROSO se incluye a proposito: un vale que ya estaba
        // atrasado (su corte anterior se vencio) sigue generando su quincena
        // normal en el siguiente corte, aparte del arrastre de la atrasada
        // (carryover, abajo) — asi como se ve en el ejemplo de la pizarra.
        // whereDate() en vez de whereBetween() con las fechas en texto: aunque
        // payment_due_date es una columna DATE de verdad (sin hora) tanto en
        // MySQL como en la definicion de la migracion, el cast 'date' de
        // Eloquent serializa el valor completo como "Y-m-d H:i:s" al guardar
        // -- MySQL igual lo guarda limpio porque su columna DATE no admite
        // hora, pero en SQLite (sin tipos reales, usado por las pruebas
        // automatizadas) se guarda el texto tal cual con la hora pegada, y
        // comparado por texto contra el limite superior de whereBetween
        // ("2026-08-29" sin hora) queda FUERA por unos caracteres de mas.
        // whereDate() envuelve la columna en DATE(...) antes de comparar, asi
        // que no importa si el valor guardado trae hora o no -- se compara
        // solo la fecha, en cualquiera de los dos motores.
        // whereColumn('installments_billed', '<', 'total_fortnights'): un vale
        // que ya se facturo por completo (llego a su ultima quincena, ej.
        // 8/8) pero sigue sin pagarse NO debe seguir generando quincenas
        // "nuevas" cada corte (9/8, 10/8...  que ni existen) -- revision
        // del profesor: debe quedarse congelado en la ultima quincena real
        // que le tocaba, y esa misma se sigue arrastrando de corte en corte
        // (ver el loop de carryover, abajo, que preserva installment_number
        // tal cual) acumulando multa cada vez que su relacion se vence sin
        // pagarse (ver MarkOverdueRelationsService). Sin este filtro, este
        // mismo bloque de abajo seguiria creando un item "normal" con
        // installment_number = installments_billed + 1 cada corte, sin
        // limite, aunque ya no exista esa quincena en el vale.
        $vouchers = Voucher::query()
            ->where('distributor_id', $distributor->id)
            ->whereIn('status', [VoucherStatus::ACTIVO, VoucherStatus::PAGO_PARCIAL, VoucherStatus::MOROSO])
            ->whereColumn('installments_billed', '<', 'total_fortnights')
            ->whereDate('payment_due_date', '>=', $periodStart->toDateString())
            ->whereDate('payment_due_date', '<=', $periodEnd->toDateString())
            ->whereNotIn('id', $alreadyIncludedVoucherIds)
            ->orderBy('id')
            ->get();

        if ($relation === null && $previousRelation === null && $vouchers->isEmpty()) {
            return;
        }

        if ($relation !== null && $vouchers->isEmpty()) {
            // Ya existe la relacion de esta distribuidora en este corte y no
            // hay vales nuevos que agregarle -- nada que hacer.
            return;
        }

        // payment_due_days ("dias de gracia") ya NO se le suma a la fecha limite
        // de pago de la relacion: la distribuidora debe liquidar el corte para
        // el mismo dia/hora en que cierra su periodo (periodEnd), sin dias
        // extra — no era parte de la especificacion del proyecto, solo se
        // habia asumido asi. payment_due_days se sigue usando abajo, pero
        // solo para calcular la ventana de "pago anticipado" del calendario
        // de cada VALE (ver DisburseVoucherService/ApproveVoucherService),
        // que es un concepto aparte del vencimiento del corte.
        $dueDays = (int) ($cutoff->branch->branchSetting?->payment_due_days ?? 15);
        $frequencyDays = (int) ($cutoff->branch->branchSetting?->payment_frequency_days ?? 14);

        if ($relation === null) {
            $relation = CutoffRelation::query()->create([
                'cutoff_id' => $cutoff->id,
                'distributor_id' => $distributor->id,
                'previous_relation_id' => $previousRelation?->id,
                'relation_number' => 'REL-'.$cutoff->id.'-'.$distributor->id,
                'payment_reference' => 'REF-'.mb_strtoupper(mb_substr(md5(uniqid((string) $distributor->id, true)), 0, 10)),
                'payment_due_date' => $periodEnd->toDateString(),
                // El "periodo" que se le muestra a la distribuidora para esta
                // relacion es el mismo periodo del corte (periodStart-periodEnd),
                // no una ventana aparte despues del cierre.
                'early_payment_start_date' => $periodStart->toDateString(),
                'early_payment_end_date' => $periodEnd->toDateString(),
                'credit_limit_snapshot' => $distributor->credit_limit,
                'available_credit_snapshot' => $distributor->available_credit,
                'points_snapshot' => $distributor->current_points,
                'status' => CutoffRelationStatus::GENERADA,
                'generated_at' => now(),
            ]);
        }

        foreach ($vouchers as $voucher) {
            $paymentAmount = round((float) $voucher->fortnightly_payment_amount, 2);
            $commission = $this->calculateDistributorCommission($voucher);
            $lineTotal = $this->calculateNetRemit($voucher, $commission);

            // El numero de quincena viene de installments_billed (cuantas
            // quincenas ya se le FACTURARON a este vale), no de payments_made
            // (cuantas ya se LIQUIDARON). Si se usara payments_made, un vale
            // que nunca se paga se quedaria facturando "quincena 1" para
            // siempre, porque payments_made nunca avanza sin un pago — el
            // calendario de facturacion no debe esperar a que paguen.
            $installmentNumber = $voucher->installments_billed + 1;

            CutoffRelationItem::query()->create([
                'cutoff_relation_id' => $relation->id,
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'product_name_snapshot' => $voucher->financialProduct?->name ?? 'Producto',
                'payments_made' => $voucher->payments_made,
                'total_payments' => $voucher->total_fortnights,
                'is_late_payment' => false,
                'installment_number' => $installmentNumber,
                'accumulated_late_installments' => 0,
                // payment_amount es la quincena COMPLETA que la distribuidora ya le
                // cobró al cliente (incluye su comisión de categoría — ver
                // FinancialCalculationService, ya no se descuenta ahí). Aquí, en el
                // corte de relación, sí se descuenta: la distribuidora no le va a
                // pagar su propia comisión a la sucursal, se la queda como
                // ganancia. line_total_amount (ver calculateNetRemit) NO sale de
                // restarle commission a este payment_amount ya redondeado -- se
                // calcula aparte, sobre el total exacto sin redondear, porque
                // payment_amount ya perdió sus centavos al piso-earse para el
                // cobro del cliente y restarle ahí la comisión desfasaría el
                // remanente. Si no paga a tiempo, MarkOverdueRelationsService le
                // suma de vuelta esa comisión (ya no gana nada) más la multa.
                'commission_amount' => $commission,
                'payment_amount' => $paymentAmount,
                'late_fee_amount' => 0.00,
                'commission_forfeited_amount' => 0.00,
                'line_total_amount' => $lineTotal,
            ]);

            // Se factura esta quincena: el vale avanza su calendario (siguiente
            // quincena programada) YA, sin importar si esta se llega a pagar o
            // no — eso se resuelve despues (a tiempo, atrasada, o arrastrada).
            // Antes esto se hacia en SettleCutoffRelationService, solo cuando
            // se pagaba; asi, un vale nunca pagado jamas volvia a "vencer" su
            // siguiente quincena y quedaba facturandose la misma una y otra
            // vez cada corte (el bug que reporto el usuario: "no aumentan las
            // quincenas").
            $nextDueDate = Carbon::parse($voucher->payment_due_date)->addDays($frequencyDays);

            $voucher->update([
                'installments_billed' => $installmentNumber,
                'payment_due_date' => $nextDueDate->toDateString(),
                'early_payment_start_date' => $nextDueDate->copy()->subDays($dueDays - 1)->toDateString(),
                'early_payment_end_date' => $nextDueDate->copy()->subDays($dueDays - $frequencyDays)->toDateString(),
            ]);
        }

        if ($previousRelation !== null) {
            $unpaidItems = $previousRelation->items()->get();

            foreach ($unpaidItems as $item) {
                // Si la relación anterior quedó PARCIAL (se conciliaron uno o
                // varios depósitos que no cubrieron todo), previous_paid_amount
                // ya trae cuánto de ESTE item se alcanzó a cubrir (ver
                // SettleCutoffRelationService::applyPartialPayment). Lo que
                // realmente sigue adeudado -- y lo único que debe arrastrarse
                // al corte nuevo -- es el remanente, no el monto original
                // completo otra vez: antes, un depósito parcial cubría de
                // hecho una parte de la deuda pero la siguiente quincena
                // seguía mostrando el adeudo completo sin descontar nada de
                // lo ya pagado.
                $alreadyPaid = round((float) $item->previous_paid_amount, 2);
                $itemRemaining = round((float) $item->line_total_amount - $alreadyPaid, 2);

                if ($itemRemaining <= 0.005) {
                    // Este item en particular ya quedó cubierto por completo
                    // (puede pasar en una relación con varios items donde el
                    // pago alcanzó para unos vales pero no para otros): ese
                    // vale sí liquidó su quincena, no hay nada que arrastrar.
                    $this->settleCutoffRelationService->advanceVoucherForItem($item);

                    continue;
                }

                CutoffRelationItem::query()->create([
                    'cutoff_relation_id' => $relation->id,
                    'voucher_id' => $item->voucher_id,
                    'customer_id' => $item->customer_id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'payments_made' => $item->payments_made,
                    'total_payments' => $item->total_payments,
                    // Un item de arrastre SIEMPRE es una quincena que ya se venció sin
                    // pagarse (por eso existe como arrastre) -- antes esto se ponia en
                    // false a la fuerza, y la UI (badge "A tiempo"/"Atrasado") terminaba
                    // mostrando como "a tiempo" una deuda que en realidad ya viene con
                    // multa. Se hereda el flag real del item original (o se deriva de si
                    // trae multa, por si algun dato viejo no lo tuviera marcado).
                    'is_late_payment' => $item->is_late_payment || (float) $item->late_fee_amount > 0,
                    'installment_number' => $item->installment_number,
                    'accumulated_late_installments' => $item->accumulated_late_installments,
                    'commission_amount' => 0.00,
                    // El remanente (ya neto de lo que se alcanzó a cubrir con
                    // pagos parciales anteriores), no el monto original del
                    // item -- ver comentario arriba.
                    'payment_amount' => $itemRemaining,
                    // La multa NO se resetea a 0 aquí: payment_amount/line_total_amount
                    // ya incluyen la multa que se le sumó cuando la relación
                    // anterior se venció (MarkOverdueRelationsService) -- si
                    // aquí se pusiera en 0, la columna "Recargo" (y el total
                    // de recargos de la relación) dejarían de reflejar esa
                    // multa ya cobrada, aunque siga incluida en el monto.
                    'late_fee_amount' => $item->late_fee_amount,
                    // La comision perdida acumulada tambien se arrastra tal
                    // cual (ver MarkOverdueRelationsService, que es quien la
                    // sigue incrementando si esta MISMA quincena vuelve a
                    // vencerse sin pagarse y es la final del vale).
                    'commission_forfeited_amount' => $item->commission_forfeited_amount,
                    'line_total_amount' => $itemRemaining,
                    // Arranca en 0 en la relación nueva: previous_paid_amount
                    // es "cuánto se cubrió DE ESTE item en ESTA relación", no
                    // un acumulado histórico entre relaciones -- ya se reflejó
                    // descontando el remanente arriba.
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

        // Los totales de la relacion se recalculan sumando TODOS sus items en
        // la base de datos (no acumulando solo los que se acaban de agregar
        // en esta llamada) -- asi generateRelation() es seguro de llamar mas
        // de una vez para la misma distribuidora+corte (ver mas arriba): cada
        // llamada deja los totales consistentes con el estado completo de la
        // relacion, incluya vales de esta llamada, de una anterior, o
        // arrastre. Un item de arrastre siempre trae origin_relation_id; uno
        // de quincena normal de este periodo, nunca.
        $items = $relation->items()->get();
        $normalItems = $items->whereNull('origin_relation_id');
        $carryoverItems = $items->whereNotNull('origin_relation_id');

        $relation->update([
            'total_payment' => round((float) $normalItems->sum('payment_amount'), 2),
            'total_commission' => round((float) $normalItems->sum('commission_amount'), 2),
            // Las quincenas normales de este corte nunca traen multa (eso se
            // decide despues, si la relacion se vence) -- lo unico que puede
            // traer multa aqui es un arrastre de una relacion anterior que ya
            // se vencio.
            'total_late_fees' => round((float) $carryoverItems->sum('late_fee_amount'), 2),
            'total_carryover_received' => round((float) $carryoverItems->sum('line_total_amount'), 2),
            // Lo que la distribuidora debe remitir: la suma de line_total_amount
            // de TODOS los items (normales + arrastre, ya netos de comision cada
            // uno -- ver calculateNetRemit).
            'total_amount_due' => round((float) $items->sum('line_total_amount'), 2),
        ]);
    }

    /**
     * Utilidad de la distribuidora sobre la quincena normal de un vale.
     *
     * La utilidad total del vale (`distributor_profit_amount`) se cotiza sobre el
     * PRINCIPAL, no sobre el total a pagar (que ya incluye comisión de apertura,
     * seguro e interés) — ver `FinancialCalculationService::calculateVoucherSnapshot()`.
     * Se reparte en partes iguales entre las quincenas (distributor_profit_amount /
     * total_fortnights): esta funcion solo se llama para la quincena normal de este
     * periodo (nunca para un arrastre, que siempre se genera con comision 0 — ver
     * el loop de carryover, abajo), asi que siempre es exactamente una quincena.
     *
     * Antes se repartia proporcionalmente a que fraccion de la deuda total
     * representaba el pago (`profitTotal * paymentAmount / totalDebt`), pero
     * payment_amount ya viene redondeado al piso para el cobro del cliente
     * (FinancialCalculationService), asi que esa proporcion nunca daba
     * exactamente 1/N y la comision salia con centavos de mas o de menos
     * (ej. $112.48 en vez de $112.50 sobre 8 quincenas). Repartir en partes
     * iguales evita ese problema.
     */
private function calculateDistributorCommission(Voucher $voucher): float
    {
        $profitTotal = (float) $voucher->distributor_profit_amount;
        $fortnights = $voucher->total_fortnights;

        if ($profitTotal <= 0 || $fortnights <= 0) {
            return 0.0;
        }

        return round($profitTotal / $fortnights, 2);
    }

    /**
     * Lo que la distribuidora debe remitir a la sucursal por la quincena
     * normal de un vale: la quincena completa (sin redondear al piso) menos
     * su comisión, redondeado al piso al final — igual que
     * FinancialCalculationService redondea el cobro al cliente al final, no
     * antes. No se parte de `payment_amount` (que ya viene redondeado al
     * piso para el cliente) porque restarle la comisión ahí perdería el
     * mismo centavo dos veces (una vez al redondear el cobro del cliente, y
     * otra vez al restar la comisión) y el remanente saldría descuadrado
     * (ej. $2,424.52 en vez de $2,425.00).
     */
    private function calculateNetRemit(Voucher $voucher, float $commission): float
    {
        $fortnights = $voucher->total_fortnights;

        if ($fortnights <= 0) {
            return 0.0;
        }

        $grossPerFortnight = ((float) $voucher->total_debt_amount) / $fortnights;

        return floor($grossPerFortnight - $commission);
    }
}
