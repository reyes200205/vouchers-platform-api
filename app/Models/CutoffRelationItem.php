<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cutoff_relation_id',
    'voucher_id',
    'customer_id',
    'product_name_snapshot',
    'payments_made',
    'total_payments',
    'is_late_payment',
    'installment_number',
    'accumulated_late_installments',
    'commission_amount',
    'payment_amount',
    'late_fee_amount',
    'commission_forfeited_amount',
    'line_total_amount',
    'previous_paid_amount',
    'origin_cutoff_id',
    'origin_relation_id',
])]
final class CutoffRelationItem extends Model
{
    public $timestamps = false;

    protected $casts = [
        'is_late_payment' => 'boolean',
        'commission_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'commission_forfeited_amount' => 'decimal:2',
        'line_total_amount' => 'decimal:2',
        'previous_paid_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<CutoffRelation, $this>
     */
    public function cutoffRelation(): BelongsTo
    {
        return $this->belongsTo(CutoffRelation::class);
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Cutoff, $this>
     */
    public function originCutoff(): BelongsTo
    {
        return $this->belongsTo(Cutoff::class, 'origin_cutoff_id');
    }

    /**
     * @return BelongsTo<CutoffRelation, $this>
     */
    public function originRelation(): BelongsTo
    {
        return $this->belongsTo(CutoffRelation::class, 'origin_relation_id');
    }

    /**
     * @return HasMany<DistributorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DistributorPayment::class, 'item_id');
    }
}
