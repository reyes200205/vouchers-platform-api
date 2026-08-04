<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'person_id',
    'customer_code',
    'status',
    'notes',
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
    protected $casts = [
        'status' => CustomerStatus::class,
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
}
