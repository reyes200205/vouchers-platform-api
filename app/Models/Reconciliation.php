<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_payment_id',
    'bank_transaction_id',
    'reconciled_by_user_id',
    'verified_by_user_id',
    'verified_at',
    'reconciled_at',
    'reconciled_amount',
    'amount_difference',
    'status',
    'notes',
])]
final class Reconciliation extends Model
{
    public $timestamps = false;

    protected $casts = [
        'reconciled_at' => 'datetime',
        'verified_at' => 'datetime',
        'reconciled_amount' => 'decimal:2',
        'amount_difference' => 'decimal:2',
        'status' => ReconciliationStatus::class,
    ];

    /**
     * @return BelongsTo<DistributorPayment, $this>
     */
    public function distributorPayment(): BelongsTo
    {
        return $this->belongsTo(DistributorPayment::class);
    }

    /**
     * @return BelongsTo<BankTransaction, $this>
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }
}
