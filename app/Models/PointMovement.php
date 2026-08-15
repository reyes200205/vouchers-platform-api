<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'voucher_id',
    'cutoff_id',
    'customer_payment_id',
    'transaction_type',
    'points',
    'point_value_snapshot',
    'reason',
    'transaction_date',
])]
final class PointMovement extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'transaction_type' => PointMovementType::class,
        'points' => 'decimal:2',
        'point_value_snapshot' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<Cutoff, $this>
     */
    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(Cutoff::class);
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }
}
