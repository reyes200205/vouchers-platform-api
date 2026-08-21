<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deshace la captura de un pago de cliente en la bitácora. Como
 * RecordCustomerPaymentService ya no mueve el saldo/estatus del vale ni
 * otorga puntos (ver su docblock), aquí tampoco hay nada de eso que
 * revertir — solo se marca el registro como reversado.
 */
final class ReverseCustomerPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, CustomerPayment $payment, array $data): CustomerPayment
    {
        if ($payment->reversed_at !== null) {
            abort(422, 'El pago ya fue reversado.');
        }

        return DB::transaction(function () use ($user, $payment, $data): CustomerPayment {
            $payment->update([
                'reversed_at' => now(),
                'reversed_by_user_id' => $user->id,
                'reversal_reason' => $data['reason'] ?? null,
            ]);

            return $payment->refresh();
        });
    }
}