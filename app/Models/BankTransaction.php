<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_bank_account_id',
    'branch_id',
    'reference',
    'transaction_date',
    'transaction_time',
    'amount',
    'transaction_type',
    'transaction_number',
    'payer_name',
    'raw_description',
])]
final class BankTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'branch_id' => 'integer',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'company_bank_account_id');
    }

    /**
     * @return HasOne<Reconciliation, $this>
     */
    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class);
    }
}
