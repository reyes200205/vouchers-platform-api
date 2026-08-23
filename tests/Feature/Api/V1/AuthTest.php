<?php

declare(strict_types=1);

use App\Mail\OtpCodeMail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function attachSuperAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['code' => 'super-admin'], ['name' => 'super-admin']);
    $user->businessRoles()->attach($role, [
        'branch_id' => null,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
}

describe('Login', function (): void {
    it('logs in with valid credentials', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'username', 'roles'],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    });

    it('updates last_login_at and login_channel on successful login', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
            'login_channel' => 'WEB',
        ]);

        expect($user->last_login_at)->toBeNull();

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
            'channel' => 'MOVIL',
        ])->assertStatus(200);

        $user->refresh();

        expect($user->last_login_at)->not->toBeNull();
        expect($user->login_channel->value)->toBe('MOVIL');
    });

    it('records an audit log entry on successful login', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertStatus(200);

        expect(AuditLog::query()->where('event_type', 'LOGIN')->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('returns the role code so the frontend can route by role', function (): void {
        $branch = Branch::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'verifier'], ['name' => 'verifier']);
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);
        $user->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'roles' => [
                            ['code' => 'verifier', 'branch_id' => $branch->id],
                        ],
                    ],
                ],
            ]);
    });

    it('fails login with invalid credentials', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Credenciales invalidas.',
            ]);
    });

    it('fails login with non-existent user', function (): void {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    });

    it('fails login if turnstile is enabled but token is missing', function (): void {
        config(['services.turnstile.enabled' => true]);

        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cf-turnstile-response');
    });

    it('fails login if turnstile is enabled but token verification fails', function (): void {
        config(['services.turnstile.enabled' => true]);
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Illuminate\Support\Facades\Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Illuminate\Support\Facades\Http::response([
                'success' => false,
            ], 200),
        ]);

        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
            'cf-turnstile-response' => 'invalid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cf-turnstile-response');
    });

    it('logs in successfully if turnstile is enabled and token verification passes', function (): void {
        config(['services.turnstile.enabled' => true]);
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Illuminate\Support\Facades\Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Illuminate\Support\Facades\Http::response([
                'success' => true,
            ], 200),
        ]);

        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
            'cf-turnstile-response' => 'valid-token',
        ]);

        $response->assertStatus(200);
    });
});

describe('OTP / MFA', function (): void {
    it('does not require OTP for a role outside otp_required_role_codes', function (): void {
        $branch = Branch::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'verifier'], ['name' => 'verifier']);
        $user = User::factory()->create(['password_hash' => bcrypt('password123')]);
        $user->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '')
            ->assertJsonMissingPath('data.requires_otp');
    });

    it('returns a challenge instead of a token when the role requires OTP', function (): void {
        Mail::fake();

        $user = User::factory()->create(['password_hash' => bcrypt('password123')]);
        attachSuperAdminRole($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['requires_otp' => true]])
            ->assertJsonPath('data.challenge_id', fn ($id) => is_string($id) && $id !== '')
            ->assertJsonMissingPath('data.token');

        Mail::assertSent(OtpCodeMail::class, fn ($mail) => $mail->hasTo($user->person->email));

        expect(AuditLog::query()->where('event_type', 'MFA_CHALLENGE_SENT')->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('verifies the OTP and completes login', function (): void {
        Mail::fake();

        $user = User::factory()->create(['password_hash' => bcrypt('password123')]);
        attachSuperAdminRole($user);

        $challengeId = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->json('data.challenge_id');

        $code = null;
        Mail::assertSent(OtpCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $response = $this->postJson('/api/v1/auth/mfa/verify', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '');

        $user->refresh();
        expect($user->last_login_at)->not->toBeNull();

        expect(AuditLog::query()->where('event_type', 'MFA_VERIFIED')->where('user_id', $user->id)->exists())->toBeTrue();
        expect(AuditLog::query()->where('event_type', 'LOGIN')->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('rejects an incorrect OTP code and keeps the challenge alive', function (): void {
        Mail::fake();

        $user = User::factory()->create(['password_hash' => bcrypt('password123')]);
        attachSuperAdminRole($user);

        $challengeId = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->json('data.challenge_id');

        $response = $this->postJson('/api/v1/auth/mfa/verify', [
            'challenge_id' => $challengeId,
            'code' => '000000',
        ]);

        $response->assertStatus(422);

        expect(AuditLog::query()->where('event_type', 'MFA_FAILED')->where('user_id', $user->id)->exists())->toBeTrue();

        // El challenge sigue vivo: reintentar con el codigo correcto todavia funciona.
        $code = null;
        Mail::assertSent(OtpCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/v1/auth/mfa/verify', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertStatus(200);
    });

    it('rejects verification with an unknown or expired challenge_id', function (): void {
        $response = $this->postJson('/api/v1/auth/mfa/verify', [
            'challenge_id' => 'nonexistent-challenge',
            'code' => '123456',
        ]);

        $response->assertStatus(401);
    });

    it('resend issues a new code and invalidates the previous one', function (): void {
        Mail::fake();

        $user = User::factory()->create(['password_hash' => bcrypt('password123')]);
        attachSuperAdminRole($user);

        $challengeId = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->json('data.challenge_id');

        $firstCode = null;
        Mail::assertSent(OtpCodeMail::class, function ($mail) use (&$firstCode) {
            $firstCode = $mail->code;

            return true;
        });

        $resendResponse = $this->postJson('/api/v1/auth/mfa/resend', ['challenge_id' => $challengeId]);
        $resendResponse->assertStatus(200);

        Mail::assertSent(OtpCodeMail::class, 2);

        expect(AuditLog::query()->where('event_type', 'MFA_CHALLENGE_RESENT')->where('user_id', $user->id)->exists())->toBeTrue();

        // El codigo anterior ya no sirve.
        $this->postJson('/api/v1/auth/mfa/verify', [
            'challenge_id' => $challengeId,
            'code' => $firstCode,
        ])->assertStatus(422);
    });
});

describe('Logout', function (): void {
    it('logs out authenticated user', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    });

    it('fails logout without authentication', function (): void {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    });
});

describe('Me', function (): void {
    it('returns authenticated user data', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'username', 'roles'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);
    });

    it('fails without authentication', function (): void {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    });

    it('exposes the pre-vale max amount when the distributor has 100% of credit available', function (): void {
        $user = User::factory()->create();
        Distributor::factory()->create([
            'person_id' => $user->person_id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        // 50% de 20,000 + tolerancia de 500 (defaults de branch_settings) = 10,500.
        $response->assertStatus(200)
            ->assertJsonPath('data.distributor.pre_vale_max_amount', fn ($v) => (float) $v === 10500.0);
    });

    it('does not limit the pre-vale amount when the distributor does not have 100% of credit available', function (): void {
        $user = User::factory()->create();
        Distributor::factory()->create([
            'person_id' => $user->person_id,
            'credit_limit' => 20000,
            'available_credit' => 12000,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.distributor.pre_vale_max_amount', null);
    });

    it('reapplies the pre-vale limit when a credit increase reactivation is pending', function (): void {
        $user = User::factory()->create();
        Distributor::factory()->create([
            'person_id' => $user->person_id,
            'credit_limit' => 20000,
            'available_credit' => 12000,
            'prevale_required_after_credit_increase_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        // 50% de 12,000 (disponible) + tolerancia de 500 = 6,500.
        $response->assertStatus(200)
            ->assertJsonPath('data.distributor.pre_vale_max_amount', fn ($v) => (float) $v === 6500.0);
    });
});
