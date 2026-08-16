<?php

declare(strict_types=1);

namespace App\Http\Requests\Credit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class PreAuthorizeCreditIncreaseRequest extends FormRequest
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
            'pre_authorized_amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'decision_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}