<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'filename',
    'file_hash',
    'imported_by_user_id',
    'branch_id',
    'row_count',
    'error_count',
    'errors_json',
    'status',
    'imported_at',
])]
final class BankTransactionImport extends Model
{
    public $timestamps = false;

    protected $casts = [
        'errors_json' => 'array',
        'status' => ImportStatus::class,
        'imported_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}