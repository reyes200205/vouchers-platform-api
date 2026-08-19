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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $guard_name = 'web';

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Registro de Distribuidora asociado a este usuario (cuando su rol de negocio es `distributor`).
     *
     * @return HasOne<Distributor, $this>
     */
    public function distributor(): HasOne
    {
        return $this->hasOne(Distributor::class, 'person_id', 'person_id');
    }

    /**
     * Roles de negocio asignados (vía tabla pivot de Spatie `model_has_roles`).
     *
     * @return MorphToMany
     */
    public function businessRoles(): MorphToMany
    {
        return $this->morphToMany(
            \Spatie\Permission\Models\Role::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        )->withPivot(['branch_id', 'assigned_at', 'revoked_at', 'is_primary']);
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

        if ($allowedRoleCodes === []) {
            return false;
        }

        $globalRoleCodes = config('business-authorization.global_role_codes', []);
        $allowedGlobalRoles = array_intersect($allowedRoleCodes, $globalRoleCodes);

        $query = $this->businessRoles()->whereIn('roles.name', $allowedRoleCodes);

        if ($branchId !== null) {
            return $query->where(function ($q) use ($branchId, $allowedGlobalRoles) {
                $q->where('model_has_roles.branch_id', $branchId);
                if ($allowedGlobalRoles !== []) {
                    $q->orWhere(function ($sq) use ($allowedGlobalRoles) {
                        $sq->whereNull('model_has_roles.branch_id')
                           ->whereIn('roles.name', $allowedGlobalRoles);
                    });
                }
            })->exists();
        }

        return $query->exists();
    }

    public function hasGlobalBusinessRole(): bool
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);
        $hasGlobal = $this->hasAnyRole(config('business-authorization.global_role_codes', []));
        $registrar->setPermissionsTeamId($originalTeamId);
        return $hasGlobal;
    }

    public function isGeneralManager(): bool
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);
        $isGm = $this->hasRole('general_manager');
        $registrar->setPermissionsTeamId($originalTeamId);
        return $isGm;
    }

    /**
     * @return list<int>
     */
    public function activeBusinessBranchIds(): array
    {
        return $this->businessRoles()
            ->whereNotNull('model_has_roles.branch_id')
            ->pluck('model_has_roles.branch_id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();
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
