<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBranchSettingRequest extends FormRequest
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
            'cutoff_day' => ['nullable', 'integer', 'between:1,31'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
            'payment_frequency_days' => ['sometimes', 'integer', 'min:1'],
            'payment_due_days' => ['sometimes', 'integer', 'min:1'],
            'default_credit_limit' => ['sometimes', 'decimal:0,2', 'min:0'],
            'insurance_rates_json' => ['nullable', 'array'],
            'opening_commission_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'biweekly_interest_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'late_payment_penalty_amount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'auto_increase_threshold' => ['nullable', 'decimal:0,2', 'min:0'],
            'minimum_score_increase_percentage' => ['sometimes', 'decimal:0,2', 'between:0,100'],
            'category_settings_json' => ['nullable', 'array'],
            'financial_product_settings_json' => ['nullable', 'array'],
            'voucher_amount_step' => ['sometimes', 'integer', 'in:100,500'],
            'pre_vale_max_percentage' => ['sometimes', 'decimal:0,2', 'between:0,100'],
            'pre_vale_tolerance_amount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'point_value_mxn' => ['sometimes', 'decimal:0,2', 'min:0'],
        ];
    }
}
