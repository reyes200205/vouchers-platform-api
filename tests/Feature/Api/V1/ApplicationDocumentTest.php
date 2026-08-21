<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInWithRoleForDocuments(User $user, string $roleCode, ?Branch $branch = null): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch?->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Coordinator application documents', function (): void {
    it('lets a coordinator upload an INE/proof of address document before the application exists', function (): void {
        Storage::fake('spaces');
        config()->set('filesystems.disks.spaces', array_merge(config('filesystems.disks.spaces'), [
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'nyc3',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://nyc3.digitaloceanspaces.com',
        ]));

        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        signInWithRoleForDocuments($coordinator, 'coordinator', $branch);

        $response = $this->postJson('/api/v1/applications/documents', [
            'type' => 'id_front',
            'document' => UploadedFile::fake()->image('ine-front.jpg', 640, 480),
        ])->assertOk()
            ->assertJsonPath('data.type', 'id_front');

        $path = $response->json('data.path');
        expect($path)->toStartWith('applications/pending/id_front/');
        Storage::disk('spaces')->assertExists($path);
    });

    it('rejects an invalid document type', function (): void {
        Storage::fake('spaces');
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        signInWithRoleForDocuments($coordinator, 'coordinator', $branch);

        $this->postJson('/api/v1/applications/documents', [
            'type' => 'selfie',
            'document' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertUnprocessable();
    });

    it('forbids a role without applications.create from uploading documents', function (): void {
        Storage::fake('spaces');
        $branch = Branch::factory()->create();
        $cashier = User::factory()->create();
        signInWithRoleForDocuments($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/applications/documents', [
            'type' => 'id_front',
            'document' => UploadedFile::fake()->image('ine-front.jpg'),
        ])->assertForbidden();
    });
});
