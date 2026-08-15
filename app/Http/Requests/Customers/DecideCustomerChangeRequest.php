<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DecideCustomerChangeRequest extends FormRequest
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
            'decision' => ['required', 'in:APPROVE,REJECT'],
            'rejection_reason' => ['required_if:decision,REJECT', 'nullable', 'string', 'max:1000'],
        ];
    }
}