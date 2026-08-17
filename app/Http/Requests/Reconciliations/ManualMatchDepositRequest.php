<?php

declare(strict_types=1);

namespace App\Http\Requests\Reconciliations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ManualMatchDepositRequest extends FormRequest
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
            'cutoff_relation_id' => ['required', 'integer', 'exists:cutoff_relations,id'],
            'amount' => ['nullable', 'decimal:0,2', 'min:0.01'],
            'payment_method' => ['nullable', 'in:TRANSFER,DEPOSIT,OTHER'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}