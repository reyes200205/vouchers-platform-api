<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_type',
    'level',
    'user_id',
    'user_name',
    'user_role',
    'branch_id',
    'module',
    'description',
    'extra_data',
    'old_data',
    'ip_address',
    'user_agent',
])]
final class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'extra_data' => 'array',
        'old_data' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
