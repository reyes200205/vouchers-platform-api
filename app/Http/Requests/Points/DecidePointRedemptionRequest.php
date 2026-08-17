<?php

declare(strict_types=1);

namespace App\Http\Requests\Points;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class DecidePointRedemptionRequest extends FormRequest
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
            'decision' => ['required', 'in:APROBADO,RECHAZADO'],
            'decision_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}