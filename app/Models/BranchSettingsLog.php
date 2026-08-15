<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BranchSettingsLogEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_setting_id',
    'branch_id',
    'updated_by_user_id',
    'event_type',
    'reference_id',
    'before_changes_json',
    'after_changes_json',
])]
final class BranchSettingsLog extends Model
{
    protected $casts = [
        'event_type' => BranchSettingsLogEventType::class,
        'before_changes_json' => 'array',
        'after_changes_json' => 'array',
    ];

    /**
     * @return BelongsTo<BranchSetting, $this>
     */
    public function branchSetting(): BelongsTo
    {
        return $this->belongsTo(BranchSetting::class);
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
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
