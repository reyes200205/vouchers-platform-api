<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'requested_by_user_id',
    'approved_by_user_id',
    'change_type',
    'old_values_json',
    'new_values_json',
    'evidence_json',
    'status',
    'rejection_reason',
    'applied_at',
])]
final class CustomerChangeRequest extends Model
{
    protected $casts = [
        'change_type' => ChangeType::class,
        'old_values_json' => 'array',
        'new_values_json' => 'array',
        'evidence_json' => 'array',
        'status' => ChangeRequestStatus::class,
        'applied_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}