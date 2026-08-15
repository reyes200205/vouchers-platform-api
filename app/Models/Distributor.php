<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DistributorStatus;
use Database\Factories\DistributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'person_id',
    'application_id',
    'branch_id',
    'coordinator_user_id',
    'category_id',
    'bank_account_id',
    'distributor_number',
    'status',
    'credit_limit',
    'available_credit',
    'unlimited_credit',
    'current_points',
    'can_issue_vouchers',
    'is_external',
    'activated_at',
    'deactivated_at',
])]
final class Distributor extends Model
{
    /** @use HasFactory<DistributorFactory> */
    use HasFactory;
    protected $casts = [
        'status' => DistributorStatus::class,
        'credit_limit' => 'decimal:2',
        'available_credit' => 'decimal:2',
        'unlimited_credit' => 'boolean',
        'current_points' => 'decimal:2',
        'can_issue_vouchers' => 'boolean',
        'is_external' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
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
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    /**
     * @return BelongsTo<DistributorCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DistributorCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_distributor')
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
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * @return HasMany<CutoffRelation, $this>
     */
    public function cutoffRelations(): HasMany
    {
        return $this->hasMany(CutoffRelation::class);
    }

    /**
     * @return HasMany<DistributorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DistributorPayment::class);
    }

    /**
     * @return HasMany<PointMovement, $this>
     */
    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }

    /**
     * @return HasMany<ManagerDecisionLog, $this>
     */
    public function managerDecisionLogs(): HasMany
    {
        return $this->hasMany(ManagerDecisionLog::class);
    }

    /**
     * @return HasMany<CreditScoreHistory, $this>
     */
    public function creditScoreHistory(): HasMany
    {
        return $this->hasMany(CreditScoreHistory::class);
    }

    /**
     * @return HasMany<CreditIncreaseSuggestion, $this>
     */
    public function creditIncreaseSuggestions(): HasMany
    {
        return $this->hasMany(CreditIncreaseSuggestion::class);
    }

    /**
     * @return HasMany<CustomerTransferRequest, $this>
     */
    public function outgoingTransferRequests(): HasMany
    {
        return $this->hasMany(CustomerTransferRequest::class, 'source_distributor_id');
    }

    /**
     * @return HasMany<CustomerTransferRequest, $this>
     */
    public function incomingTransferRequests(): HasMany
    {
        return $this->hasMany(CustomerTransferRequest::class, 'destination_distributor_id');
    }
}
