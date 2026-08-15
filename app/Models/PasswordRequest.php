<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PasswordRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'approved_by_user_id',
    'status',
    'generated_token',
    'expires_at',
])]
#[Hidden([
    'generated_token',
])]
final class PasswordRequest extends Model
{
    protected $casts = [
        'status' => PasswordRequestStatus::class,
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
