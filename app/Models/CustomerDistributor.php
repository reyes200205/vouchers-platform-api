<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerDistributorRelationshipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'customer_id',
    'relationship_status',
    'prevale_approved',
    'blocked_due_to_relationship',
    'relationship_notes',
    'linked_at',
    'unlinked_at',
])]
#[Table(name: 'customer_distributor')]
final class CustomerDistributor extends Model
{
    public $timestamps = false;

    protected $casts = [
        'relationship_status' => CustomerDistributorRelationshipStatus::class,
        'prevale_approved' => 'boolean',
        'blocked_due_to_relationship' => 'boolean',
        'linked_at' => 'datetime',
        'unlinked_at' => 'datetime',
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
}
