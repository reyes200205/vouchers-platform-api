<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VoucherRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de la tabla de "Solicitudes de vale" (aprobacion, apartado propio
 * fuera de la Bandeja de Aprobaciones). A diferencia de VoucherRequestResource
 * (que usa la propia distribuidora para ver sus solicitudes), esta trae el
 * nombre/numero de la distribuidora ya resuelto, porque aqui un
 * gerente/coordinador revisa solicitudes de VARIAS distribuidoras a la vez.
 *
 * @mixin VoucherRequest
 */
final class PendingVoucherRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'distributor_id' => $this->distributor_id,
            'distributor_name' => $this->whenLoaded('distributor', fn () => $this->distributor?->person
                ? trim(($this->distributor->person->first_name ?? '').' '.($this->distributor->person->last_name ?? ''))
                : null),
            'distributor_number' => $this->whenLoaded('distributor', fn () => $this->distributor?->distributor_number),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->person
                ? trim(($this->customer->person->first_name ?? '').' '.($this->customer->person->last_name ?? ''))
                : null),
            'customer_code' => $this->whenLoaded('customer', fn () => $this->customer?->customer_code),
            // Objeto completo del cliente (mismo shape que CustomerResource, usado
            // por customers.vue) para que la cajera pueda revisar sus datos y, si
            // es su primer vale (customer.verified_at === null), verificarlo o
            // solicitar un cambio sin salir del modal de "Decidir".
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? new CustomerResource($this->customer) : null),
            'financial_product_name' => $this->whenLoaded('financialProduct', fn () => $this->financialProduct?->name),
            'requested_amount' => $this->requested_amount,
            'is_pre_vale' => $this->is_pre_vale,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
