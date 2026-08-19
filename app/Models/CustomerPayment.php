<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'voucher_id',
    'customer_id',
    'distributor_id',
    'collected_by_user_id',
    'payment_date',
    'due_date_snapshot',
    'amount',
    'payment_method',
    'is_partial',
    'affects_points',
    'notes',
    'reversed_at',
    'reversed_by_user_id',
    'reversal_reason',
])]
final class CustomerPayment extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'payment_date' => 'datetime',
        'due_date_snapshot' => 'date',
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'is_partial' => 'boolean',
        'affects_points' => 'boolean',
        'reversed_at' => 'datetime',
    ];

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
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /**
     * @return HasMany<PointMovement, $this>
     */
    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }
}
