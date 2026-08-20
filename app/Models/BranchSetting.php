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
    'voucher_expiration_days',
    'point_divisor_factor',
    'point_multiplier',
    'late_penalty_percentage',
    'updated_by_user_id',
])]
final class BranchSetting extends Model
{
    protected $casts = [
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
        'voucher_expiration_days' => 'integer',
        'late_penalty_percentage' => 'decimal:4',
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

    /**
     * Resolves the insurance amount for a given principal using the branch tariff.
     * Returns null when no tier covers the amount.
     *
     * "Monto máximo" es inclusivo: si un tramo dice 5001-8000, un vale de
     * exactamente $8000 debe seguir cayendo en ese tramo. Antes se comparaba
     * con `<` (exclusivo), así que un monto que coincidía justo con el tope de
     * un tramo no calzaba en NINGÚN tramo y el seguro se quedaba en $0.
     */
    public function insuranceAmountFor(float $principalAmount): ?float
    {
        $rates = $this->insurance_rates_json ?? [];

        foreach ($rates as $tier) {
            $min = (float) ($tier['min_amount'] ?? 0);
            $max = (float) ($tier['max_amount'] ?? PHP_FLOAT_MAX);

            if ($principalAmount >= $min && $principalAmount <= $max) {
                return (float) $tier['insurance_amount'];
            }
        }

        return null;
    }
}
