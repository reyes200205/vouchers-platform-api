<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_front_photo' => ['nullable', 'string', 'max:255'],
            'id_back_photo' => ['nullable', 'string', 'max:255'],
            'id_selfie_photo' => ['nullable', 'string', 'max:255'],
            'proof_of_address_photo' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}