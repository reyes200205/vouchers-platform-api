<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'person_id',
    'branch_id',
    'customer_code',
    'status',
    'notes',
    'verified_by_user_id',
    'verified_at',
    'id_front_photo',
    'id_back_photo',
    'id_selfie_photo',
    'proof_of_address_photo',
    'bank_account',
    'bank_clabe',
    'account_holder_name',
])]
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;
    protected $casts = [
        'status' => CustomerStatus::class,
        'verified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * @return BelongsToMany<Distributor, $this>
     */
    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'customer_distributor')
            ->withPivot([
                'relationship_status',
                'prevale_approved',
                'blocked_due_to_relationship',
                'relationship_notes',
                'linked_at',
                'unlinked_at',
            ]);
    }

    /**
     * @return HasMany<CustomerDistributor, $this>
     */
    public function customerDistributors(): HasMany
    {
        return $this->hasMany(CustomerDistributor::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * @return HasMany<CustomerTransferRequest, $this>
     */
    public function transferRequests(): HasMany
    {
        return $this->hasMany(CustomerTransferRequest::class);
    }

    /**
     * @return HasMany<CustomerChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(CustomerChangeRequest::class);
    }

    /**
     * Clientes de una sucursal.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeOfBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Clientes vinculados a una distribuidora con relacion activa.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeOfDistributor(Builder $query, int $distributorId): Builder
    {
        return $query->whereHas('customerDistributors', function (Builder $q) use ($distributorId): void {
            $q->where('distributor_id', $distributorId)
                ->where('relationship_status', 'ACTIVA');
        });
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::ACTIVO);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }
}