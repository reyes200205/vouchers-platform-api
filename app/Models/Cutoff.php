<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use Database\Factories\CutoffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'cutoff_type',
    'base_day_of_month',
    'base_time',
    'scheduled_at',
    'executed_at',
    'keep_date_on_holiday',
    'status',
    'config_snapshot_json',
    'notes',
])]
final class Cutoff extends Model
{
    /** @use HasFactory<CutoffFactory> */
    use HasFactory;

    protected $casts = [
        'cutoff_type' => CutoffType::class,
        'status' => CutoffStatus::class,
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'keep_date_on_holiday' => 'boolean',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<CutoffRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(CutoffRelation::class);
    }

    /**
     * @return HasMany<PointMovement, $this>
     */
    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }
}
