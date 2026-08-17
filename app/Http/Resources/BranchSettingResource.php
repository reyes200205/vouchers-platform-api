<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BranchSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BranchSetting
 */
final class BranchSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'cutoff_day' => $this->cutoff_day,
            'cutoff_time' => $this->cutoff_time,
            'payment_frequency_days' => $this->payment_frequency_days,
            'payment_due_days' => $this->payment_due_days,
            'default_credit_limit' => $this->default_credit_limit,
            'insurance_rates' => $this->insurance_rates_json,
            'opening_commission_percentage' => $this->opening_commission_percentage,
            'biweekly_interest_percentage' => $this->biweekly_interest_percentage,
            'late_payment_penalty_amount' => $this->late_payment_penalty_amount,
            'auto_increase_threshold' => $this->auto_increase_threshold,
            'minimum_score_increase_percentage' => $this->minimum_score_increase_percentage,
            'category_settings' => $this->category_settings_json,
            'financial_product_settings' => $this->financial_product_settings_json,
            'voucher_amount_step' => $this->voucher_amount_step,
            'pre_vale_max_percentage' => $this->pre_vale_max_percentage,
            'pre_vale_tolerance_amount' => $this->pre_vale_tolerance_amount,
            'point_value_mxn' => $this->point_value_mxn,
            'updated_by_user_id' => $this->updated_by_user_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
