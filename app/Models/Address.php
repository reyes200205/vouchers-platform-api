<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'state',
        'city',
        'address',
        'postal_code',
    ];

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
