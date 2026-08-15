<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Person
 */
final class PersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'second_last_name' => $this->second_last_name,
            'gender' => $this->gender?->value,
            'birth_date' => $this->birth_date?->toDateString(),
            'curp' => $this->curp,
            'rfc' => $this->rfc,
            'home_phone' => $this->home_phone,
            'mobile_phone' => $this->mobile_phone,
            'email' => $this->email,
            'street' => $this->street,
            'external_number' => $this->external_number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
        ];
    }
}