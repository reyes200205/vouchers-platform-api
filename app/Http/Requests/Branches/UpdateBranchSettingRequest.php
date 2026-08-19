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
            'insurance_rates' => ['nullable', 'array', 'max:10'],
            'insurance_rates.*.min_amount' => ['required_with:insurance_rates', 'decimal:0,2', 'min:0'],
            'insurance_rates.*.max_amount' => ['required_with:insurance_rates', 'decimal:0,2', 'min:0'],
            'insurance_rates.*.insurance_amount' => ['required_with:insurance_rates', 'decimal:0,2', 'min:0'],
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

    protected function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $rates = $this->input('insurance_rates');

            if (! is_array($rates) || $rates === []) {
                return;
            }

            $complete = array_filter(
                $rates,
                static fn (mixed $tier): bool => is_array($tier)
                    && array_key_exists('min_amount', $tier)
                    && array_key_exists('max_amount', $tier)
                    && array_key_exists('insurance_amount', $tier)
            );

            $normalized = [];
            foreach ($rates as $index => $tier) {
                if (! is_array($tier)
                    || ! array_key_exists('min_amount', $tier)
                    || ! array_key_exists('max_amount', $tier)
                    || ! array_key_exists('insurance_amount', $tier)) {
                    continue;
                }

                $normalized[$index] = [
                    'min_amount' => (float) $tier['min_amount'],
                    'max_amount' => (float) $tier['max_amount'],
                    'insurance_amount' => (float) $tier['insurance_amount'],
                ];
            }

            $sorted = $normalized;
            uasort($sorted, static fn (array $a, array $b): int => $a['min_amount'] <=> $b['min_amount']);

            $previousMax = null;
            foreach ($sorted as $index => $tier) {
                if ($tier['min_amount'] >= $tier['max_amount']) {
                    $validator->errors()->add(
                        "insurance_rates.{$index}",
                        'El monto mínimo debe ser menor al máximo del tramo.'
                    );
                }

                if ($previousMax !== null && $tier['min_amount'] < $previousMax) {
                    $validator->errors()->add(
                        "insurance_rates.{$index}",
                        'Los tramos de seguro no pueden traslaparse.'
                    );
                }

                $previousMax = $tier['max_amount'];
            }
        });
    }
}
