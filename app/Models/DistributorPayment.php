<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DistributorPaymentMethod;
use App\Enums\DistributorPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cutoff_relation_id',
    'distributor_id',
    'item_id',
    'company_bank_account_id',
    'amount',
    'payment_method',
    'reported_reference',
    'payment_date',
    'status',
    'notes',
    'voucher_breakdown',
    'breakdown_applied',
])]
final class DistributorPayment extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => DistributorPaymentMethod::class,
        'payment_date' => 'datetime',
        'status' => DistributorPaymentStatus::class,
        'voucher_breakdown' => 'array',
        'breakdown_applied' => 'boolean',
    ];

    /**
     * @return BelongsTo<CutoffRelation, $this>
     */
    public function cutoffRelation(): BelongsTo
    {
        return $this->belongsTo(CutoffRelation::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<CutoffRelationItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CutoffRelationItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'company_bank_account_id');
    }

    /**
     * @return HasOne<Reconciliation, $this>
     */
    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class);
    }
}
