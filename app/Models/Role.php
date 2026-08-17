<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Rol compatible con Spatie Permission y el sistema legacy.
 */
final class Role extends SpatieRole
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'guard_name',
        'branch_id',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $role): void {
            if (empty($role->code)) {
                $role->code = $role->name;
            }
        });
    }
}
