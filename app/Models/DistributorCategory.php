<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DistributorCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'commission_percentage',
    'points_per_1200',
    'late_penalty_percentage',
    'is_active',
])]
final class DistributorCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'commission_percentage' => 'decimal:4',
        'late_penalty_percentage' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Distributor, $this>
     */
    public function distributors(): HasMany
    {
        return $this->hasMany(Distributor::class, 'category_id');
    }
}
