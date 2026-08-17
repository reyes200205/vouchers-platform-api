<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
                    'user' => ['id', 'username', 'role', 'branch_id'],
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
        $role = Role::query()->firstOrCreate(['code' => 'branch_manager'], ['name' => 'Gerente de Sucursal']);
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
            'role_id' => $role->id,
            'branch_id' => $branch->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'role' => ['code' => 'branch_manager'],
                        'branch_id' => $branch->id,
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
                'message' => 'Invalid credentials',
            ]);
    });

    it('fails login with non-existent user', function (): void {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
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
                'data' => ['id', 'username', 'role', 'branch_id'],
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
});
