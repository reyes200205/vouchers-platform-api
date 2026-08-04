<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginChannel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema (1 a 1 con Person). Autenticacion por username/password_hash.
 */
#[Fillable([
    'person_id',
    'username',
    'password_hash',
    'is_active',
    'requires_vpn',
    'login_channel',
])]
#[Hidden([
    'password_hash',
    'remember_token',
])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Roles de negocio asignados (tabla propia `roles` via pivote `user_role`).
     *
     * @return BelongsToMany<Role, $this>
     */
    public function businessRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot(['branch_id', 'assigned_at', 'revoked_at', 'is_primary']);
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function capturedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'captured_by_user_id');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function coordinatedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'coordinator_user_id');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function verifierApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'assigned_verifier_id');
    }

    /**
     * Contrasena usada para autenticacion (columna `password_hash`).
     */
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    /**
     * Nombre de la columna de contrasena usada para autenticacion.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_vpn' => 'boolean',
            'login_channel' => LoginChannel::class,
            'last_login_at' => 'datetime',
        ];
    }
}
