<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'address',
    'phone',
    'is_active',
])]
final class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<BranchSetting, $this>
     */
    public function setting(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    /**
     * @return HasOne<BranchSetting, $this>
     */
    public function branchSetting(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    /**
     * @return HasMany<BranchSettingsLog, $this>
     */
    public function settingsLogs(): HasMany
    {
        return $this->hasMany(BranchSettingsLog::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return HasMany<Distributor, $this>
     */
    public function distributors(): HasMany
    {
        return $this->hasMany(Distributor::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<Cutoff, $this>
     */
    public function cutoffs(): HasMany
    {
        return $this->hasMany(Cutoff::class);
    }
}
