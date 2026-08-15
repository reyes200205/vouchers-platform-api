<?php

declare(strict_types=1);

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DecideCustomerTransferRequest extends FormRequest
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
            'comments' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['required_if:decision,REJECT', 'nullable', 'string', 'max:1000'],
        ];
    }
}