<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditIncreaseRequestStatus;
use Database\Factories\CreditIncreaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de aumento de linea de credito de una distribuidora.
 * Flujo: la distribuidora solicita, el coordinador pre-autoriza el monto y
 * el gerente (sucursal/general) toma la decision final.
 */
#[Fillable([
    'distributor_id',
    'branch_id',
    'requested_by_user_id',
    'requested_amount',
    'reason',
    'status',
    'pre_authorized_amount',
    'pre_authorized_by_user_id',
    'pre_authorized_at',
    'approved_amount',
    'decided_by_user_id',
    'decision_notes',
    'decided_at',
])]
final class CreditIncreaseRequest extends Model
{
    /** @use HasFactory<CreditIncreaseRequestFactory> */
    use HasFactory;

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'pre_authorized_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'status' => CreditIncreaseRequestStatus::class,
        'pre_authorized_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pre_authorized_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}