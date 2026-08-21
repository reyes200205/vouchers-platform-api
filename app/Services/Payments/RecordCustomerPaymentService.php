<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\VoucherStatus;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra que una cajera capturó que un cliente le pagó su quincena
 * directo a la distribuidora. ESTO ES SOLO UNA BITÁCORA INFORMATIVA: no
 * mueve el saldo, el estatus ni el calendario del vale, y no otorga
 * puntos a la distribuidora.
 *
 * Antes este servicio SÍ hacía todo eso (avanzaba payments_made/
 * current_balance/status/payment_due_date del vale y otorgaba puntos), en
 * paralelo a lo que hace el corte de relación (GenerateCutoffService al
 * facturar, SettleCutoffRelationService al liquidarse la relación). Como
 * las dos rutas eran independientes, un mismo pago de cliente terminaba
 * avanzando el vale y dándole puntos a la distribuidora DOS VECES: una vez
 * aquí (apenas la cajera lo capturaba) y otra vez cuando el corte de esa
 * distribuidora se conciliaba como pagado. SettleCutoffRelationService ya
 * documentaba que "un pago individual de cliente ya no existe como fuente
 * de verdad" — este servicio no seguía esa regla. Ahora sí: la única
 * fuente de verdad para el saldo del vale y los puntos de la distribuidora
 * es el corte (ver GenerateCutoffService / SettleCutoffRelationService).
 */
final class RecordCustomerPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Voucher $voucher, array $data): CustomerPayment
    {
        if ($voucher->status === VoucherStatus::PAGADO || $voucher->status === VoucherStatus::LIQUIDADO) {
            abort(422, 'El vale ya está pagado.');
        }

        if ($voucher->status === VoucherStatus::CANCELADO || $voucher->status === VoucherStatus::REVERSADO) {
            abort(422, 'El vale está cancelado y no admite pagos.');
        }

        $amount = (float) $data['amount'];
        $currentBalance = (float) $voucher->current_balance;

        if ($amount <= 0) {
            abort(422, 'El monto del pago debe ser mayor a cero.');
        }

        if ($amount > $currentBalance) {
            abort(422, "El pago excede el saldo pendiente del vale ($" . number_format($currentBalance, 2) . ').');
        }

        $paymentDate = $data['payment_date'] ?? now()->toDateTimeString();

        // Se guarda de referencia (para mostrar en la bitácora si el pago
        // llegó antes o después de la fecha de vencimiento vigente), pero ya
        // no decide nada aquí: ni puntos ni avance de calendario.
        $dueDateSnapshot = $voucher->payment_due_date !== null
            ? Carbon::parse($voucher->payment_due_date)->endOfDay()
            : null;

        return DB::transaction(function () use ($user, $voucher, $data, $amount, $currentBalance, $paymentDate, $dueDateSnapshot): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'distributor_id' => $voucher->distributor_id,
                'collected_by_user_id' => $user->id,
                'payment_date' => $paymentDate,
                'due_date_snapshot' => $dueDateSnapshot?->toDateString(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'EFECTIVO',
                'is_partial' => $amount < $currentBalance,
                // Ya no controla nada (ya no se otorgan puntos desde un pago
                // individual de cliente); se conserva solo como dato capturado.
                'affects_points' => (bool) ($data['affects_points'] ?? true),
                'notes' => $data['notes'] ?? null,
            ]);

            return $payment->refresh();
        });
    }
}