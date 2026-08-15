<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditIncreaseSuggestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'score',
    'suggested_increase',
    'reason_json',
    'status',
    'approved_by_user_id',
    'rejected_by_user_id',
    'decided_at',
])]
final class CreditIncreaseSuggestion extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'suggested_increase' => 'decimal:2',
        'reason_json' => 'array',
        'status' => CreditIncreaseSuggestionStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }
}
