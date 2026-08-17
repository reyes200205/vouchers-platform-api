<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('System Roles', function (): void {
    it('returns list of roles successfully', function (): void {
        // Seed the roles and permissions first
        $this->seed(\Database\Seeders\RolesAndPermissionSeeder::class);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/system/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'description',
                            'is_active',
                        ]
                    ],
                    'links',
                    'meta',
                ]
            ]);

        // Verify we got the expected count of roles (7 roles in RolesAndPermissionSeeder)
        $data = $response->json('data.data');
        expect(count($data))->toBe(7);
    });

    it('requires authentication to fetch system roles', function (): void {
        $response = $this->getJson('/api/v1/system/roles');
        $response->assertStatus(401);
    });
});
