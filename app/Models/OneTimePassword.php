<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Codigo de un solo uso (OTP) para el segundo factor de autenticacion por correo.
 * El codigo nunca se guarda en texto plano: solo su hash (ver OneTimePasswordService).
 */
#[Fillable([
    'user_id',
    'code_hash',
    'attempts',
    'expires_at',
    'consumed_at',
])]
#[Hidden([
    'code_hash',
])]
final class OneTimePassword extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
