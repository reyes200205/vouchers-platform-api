<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CutoffRelationStatus;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cutoff_id',
    'distributor_id',
    'previous_relation_id',
    'relation_number',
    'payment_reference',
    'payment_due_date',
    'early_payment_start_date',
    'early_payment_end_date',
    'credit_limit_snapshot',
    'available_credit_snapshot',
    'points_snapshot',
    'total_commission',
    'total_payment',
    'total_late_fees',
    'total_amount_due',
    'total_carryover_received',
    'status',
    'closed_by_carryover_at',
    'generated_at',
])]
final class CutoffRelation extends Model
{
    public $timestamps = false;

    protected $casts = [
        'status' => CutoffRelationStatus::class,
        'payment_due_date' => 'date',
        'early_payment_start_date' => 'date',
        'early_payment_end_date' => 'date',
        'credit_limit_snapshot' => 'decimal:2',
        'available_credit_snapshot' => 'decimal:2',
        'points_snapshot' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'total_payment' => 'decimal:2',
        'total_late_fees' => 'decimal:2',
        'total_amount_due' => 'decimal:2',
        'total_carryover_received' => 'decimal:2',
        'closed_by_carryover_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Cutoff, $this>
     */
    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(Cutoff::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<CutoffRelation, $this>
     */
    public function previousRelation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_relation_id');
    }

    /**
     * @return HasMany<CutoffRelationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CutoffRelationItem::class);
    }

    /**
     * @return HasMany<DistributorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DistributorPayment::class);
    }

    /**
     * Cuando esta relación quedó CERRADA (su deuda ya se arrastró a una
     * relación más nueva) y después se comprobó, vía ManualMatchDepositService
     * + RetroactiveReconciliationService, que el depósito real sí había
     * llegado a tiempo, esa corrección le quita la multa a sus items pero
     * NUNCA cambia el status de esta relación -- sigue siendo CERRADA para
     * siempre (así debe ser: su deuda de verdad ya se arrastró a otra
     * relación, que es la que quedó PAGADA/PARCIAL). Sin este dato, la
     * pantalla no tiene forma de distinguir una CERRADA cuyo pago se corrigió
     * retroactivamente de una CERRADA cuyo pago simplemente nunca llegó a
     * tiempo -- ambas se ven idénticas.
     *
     * @return HasOne<Reconciliation, $this>
     */
    public function retroactiveReconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class, 'original_cutoff_relation_id')
            ->whereIn('status', [ReconciliationStatus::CONCILIADA, ReconciliationStatus::CON_DIFERENCIA])
            ->latest('verified_at');
    }
}
