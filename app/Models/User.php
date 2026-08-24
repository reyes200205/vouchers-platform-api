<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginChannel;
use App\Enums\OtpVerificationResult;
use App\Services\Auth\OneTimePasswordService;
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
    'home_branch_id',
    'username',
    'password_hash',
    'is_active',
    'requires_vpn',
    'login_channel',
    'last_login_at',
    'password_confirmed_at',
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
     * Sucursal "base" de un gerente general (solo informativo, no limita permisos).
     *
     * @return BelongsTo<Branch, $this>
     */
    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
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
                    // Un rol global (general_manager/super-admin) da acceso a
                    // CUALQUIER sucursal sin importar qué branch_id traiga su
                    // propio pivot -- ese campo no lo hace "menos global":
                    // puede traer un branch_id nada más porque así se le
                    // asignó (p.ej. también quedó como gerente de una
                    // sucursal en particular, ver BranchController::update),
                    // no porque su alcance como rol global se haya limitado.
                    // Antes se exigía además branch_id NULL en esa fila, así
                    // que un general_manager cuyo propio vínculo trajera
                    // sucursal perdía silenciosamente el acceso a las demás.
                    $q->orWhereIn('roles.name', $allowedGlobalRoles);
                }
            })->exists();
        }

        return $query->exists();
    }

    /**
     * Un rol global lo es por su NOMBRE (general_manager/super-admin), no
     * por traer branch_id NULL en su fila de model_has_roles -- antes esto
     * se resolvía forzando el team_id de Spatie a null y usando hasAnyRole(),
     * lo que solo encontraba la fila si esa fila en particular no tenía
     * sucursal asignada. Si al gerente general se le asignó su propio rol
     * CON una sucursal (p.ej. "su" matriz, un patrón que sí se usa en otras
     * partes de la app), esa comprobación fallaba y lo trataba como si fuera
     * un rol de sucursal cualquiera -- perdiendo su alcance global por
     * completo. Aquí se consulta directo por nombre de rol, sin importar qué
     * branch_id traiga esa fila.
     */
    public function hasGlobalBusinessRole(): bool
    {
        return $this->businessRoles()
            ->whereIn('roles.name', config('business-authorization.global_role_codes', []))
            ->exists();
    }

    public function isGeneralManager(): bool
    {
        return $this->businessRoles()->where('roles.name', 'general_manager')->exists();
    }

    /**
     * Si alguno de los roles de negocio del usuario esta en
     * `business-authorization.otp_required_role_codes`, debe verificar un
     * codigo OTP por correo ademas de su contrasena (ver AuthController::login()).
     */
    public function requiresOtp(): bool
    {
        $requiredRoleCodes = config('business-authorization.otp_required_role_codes', []);

        if ($requiredRoleCodes === []) {
            return false;
        }

        return $this->businessRoles()->whereIn('roles.name', $requiredRoleCodes)->exists();
    }

    public function sendOneTimePassword(): void
    {
        app(OneTimePasswordService::class)->generateAndSend($this);
    }

    public function consumeOneTimePassword(string $code): OtpVerificationResult
    {
        return app(OneTimePasswordService::class)->verify($this, $code);
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
            'password_confirmed_at' => 'datetime',
        ];
    }
}
