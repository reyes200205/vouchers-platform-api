<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración global (singleton) del sistema de puntos. Solo debe existir 1 fila.
 */
#[Fillable([
    'point_divisor_factor',
    'point_multiplier',
    'point_value_mxn',
    'late_penalty_percentage',
    'updated_by_user_id',
])]
final class PointSetting extends Model
{
    protected $casts = [
        'point_value_mxn' => 'decimal:2',
        'late_penalty_percentage' => 'decimal:4',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
