<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Login', function (): void {
    it('logs in with valid credentials', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
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

    it('fails login with invalid credentials', function (): void {
        $user = User::factory()->create([
            'password_hash' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
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
        $response = $this->postJson('/api/v1/login', [
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
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    });

    it('fails logout without authentication', function (): void {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    });
});

describe('Me', function (): void {
    it('returns authenticated user data', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me');

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
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    });
});
