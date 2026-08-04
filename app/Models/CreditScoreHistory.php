<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'evaluation_month',
    'base_score',
    'final_score',
    'factors_json',
    'suggested_increase',
    'auto_applied',
])]
#[Table(name: 'credit_score_history')]
final class CreditScoreHistory extends Model
{
    protected $casts = [
        'final_score' => 'decimal:2',
        'factors_json' => 'array',
        'suggested_increase' => 'decimal:2',
        'auto_applied' => 'boolean',
    ];

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }
}
