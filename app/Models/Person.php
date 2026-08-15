<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'first_name',
    'middle_name',
    'last_name',
    'second_last_name',
    'gender',
    'birth_date',
    'curp',
    'rfc',
    'home_phone',
    'mobile_phone',
    'email',
    'street',
    'external_number',
    'neighborhood',
    'city',
    'state',
    'postal_code',
    'latitude',
    'longitude',
    'notes',
])]
final class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

protected $casts = [
        'gender' => Gender::class,
        'birth_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasOne<Distributor, $this>
     */
    public function distributor(): HasOne
    {
        return $this->hasOne(Distributor::class);
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_person_id');
    }
}
