<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointRedemptionStatus;
use Database\Factories\PointRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'branch_id',
    'requested_by_user_id',
    'points',
    'point_value_snapshot',
    'amount_mxn',
    'status',
    'decided_by_user_id',
    'decision_notes',
    'decided_at',
])]
final class PointRedemption extends Model
{
    /** @use HasFactory<PointRedemptionFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $casts = [
        'points' => 'decimal:2',
        'point_value_snapshot' => 'decimal:2',
        'amount_mxn' => 'decimal:2',
        'status' => PointRedemptionStatus::class,
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}