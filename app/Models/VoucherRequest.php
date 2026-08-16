<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherRequestStatus;
use Database\Factories\VoucherRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de emision de vale (pre-issue). La crea la distribuidora, la
 * aprueba el coordinador/gerente y, al aprobarse, se materializa en un Voucher.
 */
#[Fillable([
    'distributor_id',
    'customer_id',
    'financial_product_id',
    'branch_id',
    'requested_amount',
    'is_pre_vale',
    'status',
    'rejection_reason',
    'snapshot_json',
    'created_by_user_id',
    'decided_by_user_id',
    'decided_at',
])]
final class VoucherRequest extends Model
{
    /** @use HasFactory<VoucherRequestFactory> */
    use HasFactory;

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'is_pre_vale' => 'boolean',
        'status' => VoucherRequestStatus::class,
        'snapshot_json' => 'array',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<FinancialProduct, $this>
     */
    public function financialProduct(): BelongsTo
    {
        return $this->belongsTo(FinancialProduct::class);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}