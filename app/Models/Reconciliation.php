<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_payment_id',
    'bank_transaction_id',
    'original_cutoff_relation_id',
    'reconciled_by_user_id',
    'verified_by_user_id',
    'verified_at',
    'reconciled_at',
    'reconciled_amount',
    'amount_difference',
    'status',
    'is_retroactive_correction',
    'waived_late_fees_total',
    'notes',
])]
final class Reconciliation extends Model
{
    public $timestamps = false;

    protected $casts = [
        'reconciled_at' => 'datetime',
        'verified_at' => 'datetime',
        'reconciled_amount' => 'decimal:2',
        'amount_difference' => 'decimal:2',
        'status' => ReconciliationStatus::class,
        'is_retroactive_correction' => 'boolean',
        'waived_late_fees_total' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<DistributorPayment, $this>
     */
    public function distributorPayment(): BelongsTo
    {
        return $this->belongsTo(DistributorPayment::class);
    }

    /**
     * @return BelongsTo<CutoffRelation, $this>
     */
    public function originalCutoffRelation(): BelongsTo
    {
        return $this->belongsTo(CutoffRelation::class, 'original_cutoff_relation_id');
    }

    /**
     * @return BelongsTo<BankTransaction, $this>
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    /**
     * Sucursal efectiva de esta conciliación para validar permisos por
     * sucursal (ver App\Http\Middleware\EnsureBusinessAbility). Reconciliation
     * no tiene columna branch_id propia -- se resuelve a través de
     * distributor_payment -> cutoff_relation -> cutoff, que sí la tienen y
     * son obligatorias (no nullable) en todo el camino.
     */
    public function resolveBusinessBranchId(): ?int
    {
        return $this->distributorPayment?->cutoffRelation?->cutoff?->branch_id;
    }
}
