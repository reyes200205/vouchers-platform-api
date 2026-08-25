<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\VoucherRequestStatus;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

/**
 * Cancela solicitudes de vale (pre-issue) que nadie aprobo ni rechazo a
 * tiempo y les devuelve el credito apartado a la distribuidora.
 *
 * Hermano de CancelExpiredVouchersService: ese cubre el vale que YA se
 * aprobo pero nunca se disperso/fereo; este cubre el otro extremo, el vale
 * que ni siquiera llego a aprobarse. Ambos cuentan los dias de vencimiento
 * desde el mismo momento (la solicitud original -- ver el comentario de
 * issued_at en ApproveVoucherService), asi que la distribuidora nunca ve una
 * ventana mas larga solo porque su solicitud tardo en resolverse.
 */
final class CancelExpiredVoucherRequestsService
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            $requests = VoucherRequest::query()
                ->where('status', VoucherRequestStatus::PENDIENTE)
                ->with(['branch.setting', 'distributor'])
                ->get();

            $canceledCount = 0;

            foreach ($requests as $voucherRequest) {
                $setting = $voucherRequest->branch?->setting;
                if (! $setting || $setting->voucher_expiration_days === null) {
                    continue;
                }

                $expirationDate = $voucherRequest->created_at?->copy()->addDays((int) $setting->voucher_expiration_days);
                if ($expirationDate && now()->greaterThan($expirationDate)) {
                    $voucherRequest->update([
                        'status' => VoucherRequestStatus::CANCELADO,
                        'rejection_reason' => 'Cancelada automáticamente por vencimiento sin ser aprobada.',
                        'decided_at' => now(),
                    ]);

                    // El credito se aparto por el principal al mandar la solicitud
                    // (RequestVoucherService), no por el total a cobrar -- se
                    // devuelve exactamente eso.
                    $voucherRequest->distributor->increment('available_credit', $voucherRequest->requested_amount);
                    $canceledCount++;
                }
            }

            return $canceledCount;
        });
    }
}
