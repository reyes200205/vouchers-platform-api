<?php

declare(strict_types=1);

namespace App\Http\Requests\Vouchers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DisburseVoucherRequest extends FormRequest
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
            'transfer_reference' => ['required', 'string', 'max:100'],
            'authorized_number' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}