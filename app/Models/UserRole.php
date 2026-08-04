<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote de asignación de rol de negocio a un usuario (con sucursal opcional).
 */
#[Fillable([
    'user_id',
    'role_id',
    'branch_id',
    'assigned_at',
    'revoked_at',
    'is_primary',
])]
#[Table(name: 'user_role')]
final class UserRole extends Model
{
    public $timestamps = false;

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
