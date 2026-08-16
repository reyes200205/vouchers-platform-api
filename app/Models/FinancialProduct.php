<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementMethod;
use Database\Factories\FinancialProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'description',
    'principal_amount',
    'number_of_fortnights',
    'company_commission_percentage',
    'insurance_amount',
    'fortnightly_interest_percentage',
    'late_fee_amount',
    'disbursement_method',
    'is_active',
])]
final class FinancialProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'company_commission_percentage' => 'decimal:4',
        'insurance_amount' => 'decimal:2',
        'fortnightly_interest_percentage' => 'decimal:4',
        'late_fee_amount' => 'decimal:2',
        'disbursement_method' => DisbursementMethod::class,
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
