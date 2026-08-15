<?php

declare(strict_types=1);

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerTransferRequest extends FormRequest
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
            'destination_distributor_id' => ['required', 'integer', 'exists:distributors,id'],
            'request_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}