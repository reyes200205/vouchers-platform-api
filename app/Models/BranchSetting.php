<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'cutoff_day',
    'cutoff_time',
    'payment_frequency_days',
    'payment_due_days',
    'default_credit_limit',
    'insurance_rates_json',
    'opening_commission_percentage',
    'biweekly_interest_percentage',
    'late_payment_penalty_amount',
    'auto_increase_threshold',
    'minimum_score_increase_percentage',
    'category_settings_json',
    'financial_product_settings_json',
    'voucher_amount_step',
    'pre_vale_max_percentage',
    'pre_vale_tolerance_amount',
    'point_value_mxn',
    'updated_by_user_id',
])]
final class BranchSetting extends Model
{
    protected $casts = [
        'default_credit_limit' => 'decimal:2',
        'insurance_rates_json' => 'array',
        'opening_commission_percentage' => 'decimal:4',
        'biweekly_interest_percentage' => 'decimal:4',
        'late_payment_penalty_amount' => 'decimal:2',
        'auto_increase_threshold' => 'decimal:2',
        'minimum_score_increase_percentage' => 'decimal:2',
        'category_settings_json' => 'array',
        'financial_product_settings_json' => 'array',
        'pre_vale_max_percentage' => 'decimal:2',
        'pre_vale_tolerance_amount' => 'decimal:2',
        'point_value_mxn' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<BranchSettingsLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BranchSettingsLog::class);
    }
}
