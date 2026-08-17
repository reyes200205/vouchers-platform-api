<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerPaymentRequest extends FormRequest
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
            'voucher_id' => ['required', 'integer', 'exists:vouchers,id'],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'payment_date' => ['nullable', 'date', 'before_or_equal:now'],
            'payment_method' => ['nullable', 'in:EFECTIVO,TRANSFERENCIA'],
            'affects_points' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}