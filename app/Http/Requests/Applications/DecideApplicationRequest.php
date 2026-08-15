<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DecideApplicationRequest extends FormRequest
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
            'credit_limit' => ['required_if:decision,APPROVE', 'nullable', 'decimal:0,2', 'min:0'],
            'category_id' => ['required_if:decision,APPROVE', 'nullable', 'integer', 'exists:distributor_categories,id'],
            'coordinator_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rejection_reason' => ['required_if:decision,REJECT', 'nullable', 'string'],
        ];
    }
}
