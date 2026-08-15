<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de roles de negocio (independiente de Spatie Permission).
 */
#[Fillable([
    'code',
    'name',
    'description',
    'is_active',
])]
final class Role extends Model
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->withPivot(['branch_id', 'assigned_at', 'revoked_at', 'is_primary']);
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }
}
