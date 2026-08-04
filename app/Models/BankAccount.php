<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankAccountOwnerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta bancaria de un dueño (persona, distribuidora o empresa).
 *
 * `owner_type`/`owner_id` es una relación polimórfica manual (sin FK), ya que
 * `EMPRESA` no corresponde a un modelo/tabla propia.
 */
#[Fillable([
    'owner_type',
    'owner_id',
    'bank',
    'account_holder_name',
    'masked_account_number',
    'clabe',
    'agreement',
    'reference_base',
    'is_primary',
    'verified_at',
])]
final class BankAccount extends Model
{
    public $timestamps = true;

    const UPDATED_AT = 'updated_at';

    protected $casts = [
        'owner_type' => BankAccountOwnerType::class,
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Resuelve el dueño de la cuenta cuando es una persona o distribuidora.
     */
    public function owner(): Person|Distributor|null
    {
        return match ($this->owner_type) {
            BankAccountOwnerType::PERSONA => Person::query()->find($this->owner_id),
            BankAccountOwnerType::DISTRIBUIDORA => Distributor::query()->find($this->owner_id),
            default => null,
        };
    }
}
