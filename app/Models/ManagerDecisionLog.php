<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ManagerDecisionEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'manager_user_id',
    'application_id',
    'distributor_id',
    'event_type',
    'previous_amount',
    'new_amount',
])]
final class ManagerDecisionLog extends Model
{
    protected $casts = [
        'event_type' => ManagerDecisionEventType::class,
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }
}
