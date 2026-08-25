<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTransferRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'source_distributor_id',
    'destination_distributor_id',
    'requested_by_user_id',
    'destination_decided_by_user_id',
    'destination_decided_at',
    'coordinator_user_id',
    'coordinator_decided_at',
    'finalized_by_user_id',
    'status',
    'executed_at',
    'request_reason',
    'rejection_reason',
    'comments',
])]
final class CustomerTransferRequest extends Model
{
    protected $casts = [
        'status' => CustomerTransferRequestStatus::class,
        'executed_at' => 'datetime',
        'destination_decided_at' => 'datetime',
        'coordinator_decided_at' => 'datetime',
    ];

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
    public function sourceDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'source_distributor_id');
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function destinationDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'destination_distributor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function destinationDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_decided_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }
}