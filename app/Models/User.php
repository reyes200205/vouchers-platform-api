<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginChannel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario del sistema (1 a 1 con Person). Autenticacion por username/password_hash.
 * Cada usuario tiene exactamente un rol de negocio (role_id) y, opcionalmente, una sucursal.
 */
#[Fillable([
    'person_id',
    'username',
    'password_hash',
    'role_id',
    'branch_id',
    'is_active',
    'requires_vpn',
    'login_channel',
    'last_login_at',
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
     * Rol de negocio asignado (tabla propia `roles`).
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Sucursal a la que pertenece el usuario (nula para roles globales).
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function hasBusinessAbility(string $ability, ?int $branchId = null): bool
    {
        $abilities = config('business-authorization.abilities', []);
        $allowedRoleCodes = $abilities[$ability] ?? [];

        if ($allowedRoleCodes === [] || $this->role === null || ! in_array($this->role->code, $allowedRoleCodes, true)) {
            return false;
        }

        if ($branchId === null) {
            return true;
        }

        if ($this->branch_id === $branchId) {
            return true;
        }

        $globalRoleCodes = config('business-authorization.global_role_codes', []);

        return in_array($this->role->code, $globalRoleCodes, true);
    }

    public function hasGlobalBusinessRole(): bool
    {
        return $this->role !== null
            && in_array($this->role->code, config('business-authorization.global_role_codes', []), true);
    }

    public function isGeneralManager(): bool
    {
        return $this->role?->code === 'general_manager';
    }

    /**
     * @return list<int>
     */
    public function activeBusinessBranchIds(): array
    {
        return $this->branch_id === null ? [] : [$this->branch_id];
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
