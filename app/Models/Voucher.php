<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherStatus;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'voucher_number',
    'distributor_id',
    'customer_id',
    'financial_product_id',
    'voucher_request_id',
    'branch_id',
    'created_by_user_id',
    'approved_by_user_id',
    'disbursed_by_user_id',
    'status',
    'is_pre_vale',
    'amount',
    'company_commission_percentage_snapshot',
    'company_commission_amount',
    'insurance_amount_snapshot',
    'interest_percentage_snapshot',
    'interest_amount',
    'distributor_profit_percentage_snapshot',
    'distributor_profit_amount',
    'late_fee_amount_snapshot',
    'total_debt_amount',
    'fortnightly_payment_amount',
    'total_fortnights',
    'payments_made',
    'current_balance',
    'transfer_reference',
    'authorized_number',
    'issued_at',
    'transferred_at',
    'payment_due_date',
    'early_payment_start_date',
    'early_payment_end_date',
    'claim_reason',
    'is_canceled',
    'canceled_at',
    'notes',
])]
final class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    protected $casts = [
        'status' => VoucherStatus::class,
        'is_pre_vale' => 'boolean',
        'amount' => 'decimal:2',
        'company_commission_percentage_snapshot' => 'decimal:4',
        'company_commission_amount' => 'decimal:2',
        'insurance_amount_snapshot' => 'decimal:2',
        'interest_percentage_snapshot' => 'decimal:4',
        'interest_amount' => 'decimal:2',
        'distributor_profit_percentage_snapshot' => 'decimal:4',
        'distributor_profit_amount' => 'decimal:2',
        'late_fee_amount_snapshot' => 'decimal:2',
        'total_debt_amount' => 'decimal:2',
        'fortnightly_payment_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'issued_at' => 'datetime',
        'transferred_at' => 'datetime',
        'payment_due_date' => 'date',
        'early_payment_start_date' => 'date',
        'early_payment_end_date' => 'date',
        'is_canceled' => 'boolean',
        'canceled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<FinancialProduct, $this>
     */
    public function financialProduct(): BelongsTo
    {
        return $this->belongsTo(FinancialProduct::class);
    }

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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by_user_id');
    }

    /**
     * @return BelongsTo<VoucherRequest, $this>
     */
    public function voucherRequest(): BelongsTo
    {
        return $this->belongsTo(VoucherRequest::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * @return HasMany<PointMovement, $this>
     */
    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }

    /**
     * @return HasMany<CutoffRelationItem, $this>
     */
    public function cutoffRelationItems(): HasMany
    {
        return $this->hasMany(CutoffRelationItem::class);
    }

    /**
     * @return HasOne<SimulatedCompanyExpense, $this>
     */
    public function simulatedExpense(): HasOne
    {
        return $this->hasOne(SimulatedCompanyExpense::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->status !== VoucherStatus::APROBADO) {
            return false;
        }

        $branchSetting = $this->branch?->setting;
        if (! $branchSetting || $branchSetting->voucher_expiration_days === null) {
            return false;
        }

        $expirationDate = $this->expiration_date;
        return $expirationDate ? now()->greaterThan($expirationDate) : false;
    }

    public function getExpirationDateAttribute(): ?\Carbon\Carbon
    {
        if ($this->status !== VoucherStatus::APROBADO) {
            return null;
        }

        $branchSetting = $this->branch?->setting;
        if (! $branchSetting || $branchSetting->voucher_expiration_days === null) {
            return null;
        }

        return $this->issued_at?->copy()->addDays((int) $branchSetting->voucher_expiration_days);
    }
}
