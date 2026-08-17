<?php

declare(strict_types=1);

namespace App\Http\Requests\PointSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePointSettingRequest extends FormRequest
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
            'point_divisor_factor' => ['sometimes', 'integer', 'min:1'],
            'point_multiplier' => ['sometimes', 'integer', 'min:0'],
            'late_penalty_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
        ];
    }
}