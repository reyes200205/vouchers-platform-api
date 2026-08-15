<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'applicant_person_id',
    'branch_id',
    'captured_by_user_id',
    'coordinator_user_id',
    'assigned_verifier_id',
    'bank_account_id',
    'status',
    'initial_category_code',
    'family_data_json',
    'external_affiliations_json',
    'vehicles_json',
    'requested_credit_limit',
    'id_front_path',
    'id_back_path',
    'proof_of_address_path',
    'credit_bureau_report_path',
    'credit_bureau_result',
    'rejection_reason',
    'prevale_approved',
    'house_photos_complete',
    'taken_at',
    'submitted_at',
    'reviewed_at',
    'decided_at',
])]
final class Application extends Model
{
    protected $casts = [
        'status' => ApplicationStatus::class,
        'family_data_json' => 'array',
        'external_affiliations_json' => 'array',
        'vehicles_json' => 'array',
        'requested_credit_limit' => 'decimal:2',
        'prevale_approved' => 'boolean',
        'house_photos_complete' => 'boolean',
        'taken_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'applicant_person_id');
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
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_verifier_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasOne<ApplicationVerification, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(ApplicationVerification::class);
    }

    /**
     * @return HasOne<Distributor, $this>
     */
    public function distributor(): HasOne
    {
        return $this->hasOne(Distributor::class);
    }

    /**
     * @return HasMany<ManagerDecisionLog, $this>
     */
    public function managerDecisionLogs(): HasMany
    {
        return $this->hasMany(ManagerDecisionLog::class);
    }
}
