<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInForSpacesTest(User $user, string $roleCode): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, ['assigned_at' => now(), 'is_primary' => true]);
    Sanctum::actingAs($user);
}

describe('DigitalOcean Spaces test upload', function (): void {
    it('uploads a private test file for a general manager', function (): void {
        Storage::fake('spaces');
        config()->set('filesystems.disks.spaces', array_merge(config('filesystems.disks.spaces'), [
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'nyc3',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://nyc3.digitaloceanspaces.com',
        ]));

        $user = User::factory()->create();
        signInForSpacesTest($user, 'general_manager');

        $response = $this->postJson('/api/v1/system/storage/spaces/test-upload', [
            'file' => UploadedFile::fake()->image('evidence.png', 640, 480),
        ])->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png');

        Storage::disk('spaces')->assertExists($response->json('data.path'));
    });

    it('forbids a cashier from using the infrastructure test endpoint', function (): void {
        Storage::fake('spaces');
        $user = User::factory()->create();
        signInForSpacesTest($user, 'cashier');

        $this->postJson('/api/v1/system/storage/spaces/test-upload', [
            'file' => UploadedFile::fake()->image('evidence.png'),
        ])->assertForbidden();
    });
});