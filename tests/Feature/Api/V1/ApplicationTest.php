<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DistributorCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function attachRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
}

function signInWithRole(User $user, string $roleCode, Branch $branch): void
{
    attachRole($user, $roleCode, $branch);
    Sanctum::actingAs($user);
}

describe('Distributor onboarding', function (): void {
    it('moves an application from coordinator capture to approved distributor', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $manager = User::factory()->create();
        signInWithRole($coordinator, 'coordinator', $branch);

        $application = $this->postJson('/api/v1/applications', [
            'branch_id' => $branch->id,
            'person' => [
                'first_name' => 'Ana',
                'last_name' => 'Distribuidora',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ01',
                'rfc' => 'ABCD900101XYZ',
                'mobile_phone' => '8711234567',
                'email' => 'ana@distribuidora.com',
                'street' => 'Calle Hidalgo',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => ['children' => 2, 'applicant_age' => 28],
            'vehicles' => [['type' => 'car']],
            'requested_credit_limit' => '10000.00',
        ])->assertCreated()->assertJsonPath('data.status', 'EN_REVISION')->json('data');

        attachRole($verifier, 'verifier', $branch);
        $this->patchJson("/api/v1/applications/{$application['id']}/verifier", [
            'verifier_user_id' => $verifier->id,
        ])->assertOk();

        Sanctum::actingAs($verifier);
        $this->postJson("/api/v1/applications/{$application['id']}/verification", [
            'result' => 'VERIFICADA',
            'visit_date' => now()->toDateTimeString(),
            'checklist' => ['home_visited' => true],
            'front_photo' => 'verifications/1/front.jpg',
        ])->assertOk();

        $category = DistributorCategory::query()->create([
            'branch_id' => $branch->id,
            'code' => 'PLATA',
            'name' => 'Plata',
            'commission_percentage' => '6.0000',
        ]);
        signInWithRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/applications/{$application['id']}/decision", [
            'decision' => 'APPROVE',
            'credit_limit' => '10000.00',
            'category_id' => $category->id,
            'coordinator_user_id' => $coordinator->id,
        ])->assertOk()
            ->assertJsonPath('data.application.status', 'APROBADA')
            ->assertJsonPath('data.distributor.credit_limit', '10000.00')
            ->assertJsonStructure(['data' => ['distributor_username']]);

        $this->assertDatabaseHas('distributors', [
            'branch_id' => $branch->id,
            'status' => 'ACTIVA',
            'can_issue_vouchers' => true,
        ]);
    });

    it('shows the full application detail with applicant info and photo URLs for the branch manager deciding it', function (): void {
        // La foto de verificación se guarda en Spaces (ver VerificationPhotoController);
        // el detalle debe leerla de ahí con una URL firmada, no como ruta local.
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
        $verifier = User::factory()->create();
        $manager = User::factory()->create();
        signInWithRole($coordinator, 'coordinator', $branch);

        $application = $this->postJson('/api/v1/applications', [
            'branch_id' => $branch->id,
            'person' => [
                'first_name' => 'Ana',
                'last_name' => 'Distribuidora',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ01',
                'rfc' => 'ABCD900101XYZ',
                'mobile_phone' => '8112345678',
                'email' => 'ana@distribuidora.com',
                'street' => 'Av. Juarez 123',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => ['children' => 2, 'applicant_age' => 28],
            'vehicles' => [['type' => 'car']],
            'requested_credit_limit' => '10000.00',
            'id_front_path' => 'applications/1/id_front.jpg',
            'id_back_path' => 'applications/1/id_back.jpg',
            'proof_of_address_path' => 'applications/1/comprobante.jpg',
        ])->assertCreated()->json('data');

        attachRole($verifier, 'verifier', $branch);
        $this->patchJson("/api/v1/applications/{$application['id']}/verifier", [
            'verifier_user_id' => $verifier->id,
        ])->assertOk();

        Sanctum::actingAs($verifier);
        $this->postJson("/api/v1/applications/{$application['id']}/verification", [
            'result' => 'VERIFICADA',
            'visit_date' => now()->toDateTimeString(),
            'checklist' => ['home_visited' => true],
            'front_photo' => 'verifications/1/fachada.jpg',
        ])->assertOk();

        signInWithRole($manager, 'branch_manager', $branch);

        $this->getJson("/api/v1/applications/{$application['id']}")
            ->assertOk()
            ->assertJsonPath('data.applicant.first_name', 'Ana')
            ->assertJsonPath('data.applicant.curp', 'ABCD900101HNLXYZ01')
            ->assertJsonPath('data.applicant.mobile_phone', '8112345678')
            ->assertJsonPath('data.family_data_json.applicant_age', 28)
            ->assertJsonPath('data.id_front_url', fn ($url) => str_ends_with($url, '/storage/applications/1/id_front.jpg'))
            ->assertJsonPath('data.proof_of_address_url', fn ($url) => str_ends_with($url, '/storage/applications/1/comprobante.jpg'))
            ->assertJsonPath('data.verification.front_photo_url', fn ($url) => is_string($url)
                && str_contains($url, 'verifications/1/fachada.jpg')
                && ! str_contains($url, '/storage/verifications'));
    });

    it('forbids a branch manager from viewing an application of another branch', function (): void {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $manager = User::factory()->create();
        signInWithRole($coordinator, 'coordinator', $branch);

        $application = $this->postJson('/api/v1/applications', [
            'branch_id' => $branch->id,
            'person' => [
                'first_name' => 'Ana',
                'last_name' => 'Distribuidora',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ02',
                'rfc' => 'ABCD900101XY2',
                'mobile_phone' => '8112345678',
                'email' => 'ana2@distribuidora.com',
                'street' => 'Av. Juarez 123',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => ['applicant_age' => 28],
            'requested_credit_limit' => '10000.00',
        ])->assertCreated()->json('data');

        signInWithRole($manager, 'branch_manager', $otherBranch);

        $this->getJson("/api/v1/applications/{$application['id']}")->assertForbidden();
    });
});
