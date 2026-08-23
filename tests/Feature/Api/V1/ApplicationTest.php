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
    it('persists the full nested family_data (members, occupation, housing) captured by the coordinator', function (): void {
        // Regresion: $request->validated() poda las sub-claves de family_data
        // que no tienen su propia regla (solo family_data.applicant_age la
        // tiene), asi que members/occupation/housing se perdian en silencio
        // aunque el formulario (registro-verificacion/new.vue) si los mandaba.
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        signInWithRole($coordinator, 'coordinator', $branch);

        $response = $this->postJson('/api/v1/applications', [
            'branch_id' => $branch->id,
            'person' => [
                'first_name' => 'Ana', 'last_name' => 'Distribuidora', 'second_last_name' => 'Materno',
                'gender' => 'F', 'birth_date' => '1990-01-01', 'curp' => 'ABCD900101HNLXYZ03', 'rfc' => 'ABCD900101XY3',
                'home_phone' => '8711234568', 'mobile_phone' => '8112345670', 'email' => 'ana3@distribuidora.com',
                'street' => 'Av. Juarez 123', 'external_number' => '123', 'neighborhood' => 'Centro',
                'city' => 'Torreon', 'state' => 'Coahuila', 'postal_code' => '27000',
            ],
            'family_data' => [
                'applicant_age' => 28,
                'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 27]],
                'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
                'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
            ],
            'requested_credit_limit' => '10000.00',
        ])->assertCreated();

        $response
            ->assertJsonPath('data.family_data_json.members.0.name', 'Maria Perez')
            ->assertJsonPath('data.family_data_json.occupation.place_name', 'ACME')
            ->assertJsonPath('data.family_data_json.housing.work_reference.name', 'Juan Lopez');
    });

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
                'second_last_name' => 'Materno',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ01',
                'rfc' => 'ABCD900101XYZ',
                'home_phone' => '8711234568',
                'mobile_phone' => '8711234567',
                'email' => 'ana@distribuidora.com',
                'street' => 'Calle Hidalgo',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => [
                'children' => 2,
                'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 27]],
                'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
                'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
            ],
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
            'id_with_person_photo' => 'verifications/1/id_with_person.jpg',
            'proof_of_address_photo' => 'verifications/1/proof.jpg',
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
                'second_last_name' => 'Materno',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ01',
                'rfc' => 'ABCD900101XYZ',
                'home_phone' => '8711234568',
                'mobile_phone' => '8112345678',
                'email' => 'ana@distribuidora.com',
                'street' => 'Av. Juarez 123',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => [
                'children' => 2,
                'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 27]],
                'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
                'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
            ],
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
            'id_with_person_photo' => 'verifications/1/id_with_person.jpg',
            'proof_of_address_photo' => 'verifications/1/proof.jpg',
        ])->assertOk();

        signInWithRole($manager, 'branch_manager', $branch);

        $this->getJson("/api/v1/applications/{$application['id']}")
            ->assertOk()
            ->assertJsonPath('data.applicant.first_name', 'Ana')
            ->assertJsonPath('data.applicant.curp', 'ABCD900101HNLXYZ01')
            ->assertJsonPath('data.applicant.mobile_phone', '8112345678')
            ->assertJsonPath('data.id_front_url', fn ($url) => is_string($url)
                && str_contains($url, 'applications/1/id_front.jpg')
                && ! str_contains($url, '/storage/applications'))
            ->assertJsonPath('data.proof_of_address_url', fn ($url) => is_string($url)
                && str_contains($url, 'applications/1/comprobante.jpg')
                && ! str_contains($url, '/storage/applications'))
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
                'second_last_name' => 'Materno',
                'gender' => 'F',
                'birth_date' => '1990-01-01',
                'curp' => 'ABCD900101HNLXYZ02',
                'rfc' => 'ABCD900101XY2',
                'home_phone' => '8711234568',
                'mobile_phone' => '8112345678',
                'email' => 'ana2@distribuidora.com',
                'street' => 'Av. Juarez 123',
                'external_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Torreon',
                'state' => 'Coahuila',
                'postal_code' => '27000',
            ],
            'family_data' => [
                'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 27]],
                'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
                'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
            ],
            'requested_credit_limit' => '10000.00',
        ])->assertCreated()->json('data');

        signInWithRole($manager, 'branch_manager', $otherBranch);

        $this->getJson("/api/v1/applications/{$application['id']}")->assertForbidden();
    });
});

function createApplicationEnRevision(Branch $branch, User $coordinator, User $verifier): array
{
    signInWithRole($coordinator, 'coordinator', $branch);

    $application = test()->postJson('/api/v1/applications', [
        'branch_id' => $branch->id,
        'person' => [
            'first_name' => 'Ana',
            'last_name' => 'Distribuidora',
            'second_last_name' => 'Materno',
            'gender' => 'F',
            'birth_date' => '1990-01-01',
            'curp' => 'ABCD900101HNLXYZ01',
            'rfc' => 'ABCD900101XYZ',
            'home_phone' => '8711234568',
            'mobile_phone' => '8711234567',
            'email' => 'ana@distribuidora.com',
            'street' => 'Calle Hidalgo',
            'external_number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'Torreon',
            'state' => 'Coahuila',
            'postal_code' => '27000',
        ],
        'family_data' => [
            'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 27]],
            'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
            'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
        ],
        'requested_credit_limit' => '10000.00',
    ])->assertCreated()->json('data');

    attachRole($verifier, 'verifier', $branch);
    test()->patchJson("/api/v1/applications/{$application['id']}/verifier", [
        'verifier_user_id' => $verifier->id,
    ])->assertOk();

    return $application;
}

describe('Update application (verifier corrections)', function (): void {
    it('lets the assigned verifier correct applicant data while EN_REVISION', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        Sanctum::actingAs($verifier);

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'person' => ['mobile_phone' => '8719998888', 'street' => 'Calle Corregida 45'],
            'requested_credit_limit' => '12000.00',
        ])->assertOk()
            ->assertJsonPath('data.applicant.mobile_phone', '8719998888')
            ->assertJsonPath('data.applicant.street', 'Calle Corregida 45')
            ->assertJsonPath('data.requested_credit_limit', '12000.00')
            ->assertJsonPath('data.verifier_corrections_json.0.corrected_by_user_id', $verifier->id)
            ->assertJsonCount(3, 'data.verifier_corrections_json.0.changes');

        // GET /applications/{id} (lo que ve gerencia al decidir) debe traer
        // el mismo historial de correcciones, no solo la respuesta del PATCH.
        signInWithRole(User::factory()->create(), 'branch_manager', $branch);
        $this->getJson("/api/v1/applications/{$application['id']}")
            ->assertOk()
            ->assertJsonPath('data.verifier_corrections_json.0.changes.0.field', 'mobile_phone');
    });

    it('forbids a verifier who is not assigned to this application from editing it', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $otherVerifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        attachRole($otherVerifier, 'verifier', $branch);
        Sanctum::actingAs($otherVerifier);

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'person' => ['mobile_phone' => '8719998888'],
        ])->assertForbidden();
    });

    it('rejects edits once the application has already been verified', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        Sanctum::actingAs($verifier);
        $this->postJson("/api/v1/applications/{$application['id']}/verification", [
            'result' => 'VERIFICADA',
            'visit_date' => now()->toDateTimeString(),
            'checklist' => ['home_visited' => true],
            'front_photo' => 'verifications/1/front.jpg',
            'id_with_person_photo' => 'verifications/1/id_with_person.jpg',
            'proof_of_address_photo' => 'verifications/1/proof.jpg',
        ])->assertOk();

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'person' => ['mobile_phone' => '8719998888'],
        ])->assertStatus(422);
    });

    it('rejects an invalid CURP correction', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        Sanctum::actingAs($verifier);

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'person' => ['curp' => 'INVALID'],
        ])->assertStatus(422);
    });

    it('lets the assigned verifier correct family/occupation/housing data and vehicles', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        Sanctum::actingAs($verifier);

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'family_data' => [
                'members' => [['name' => 'Maria Perez', 'relationship' => 'Esposo(a)', 'phone' => '8710000000', 'age' => 28]],
                'occupation' => ['type' => 'trabaja', 'place_name' => 'ACME', 'position' => 'Gerente', 'phone' => '8710000001', 'years' => 3],
                'housing' => ['ownership_type' => 'propia', 'dimensions' => '150 m2', 'years_at_address' => 5, 'work_reference' => ['name' => 'Juan Lopez', 'phone' => '8710000002']],
            ],
            'vehicles' => [['brand' => 'Nissan', 'model' => 'Versa', 'year' => '2020', 'plates' => 'ABC123']],
        ])->assertOk()
            ->assertJsonPath('data.family_data_json.occupation.place_name', 'ACME')
            ->assertJsonPath('data.vehicles_json.0.brand', 'Nissan')
            ->assertJsonPath('data.verifier_corrections_json.0.changes.0.field', 'family_data')
            ->assertJsonPath('data.verifier_corrections_json.0.changes.1.field', 'vehicles');
    });

    it('forbids a coordinator from using the verifier correction endpoint', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $application = createApplicationEnRevision($branch, $coordinator, $verifier);

        Sanctum::actingAs($coordinator);

        $this->patchJson("/api/v1/applications/{$application['id']}", [
            'person' => ['mobile_phone' => '8719998888'],
        ])->assertForbidden();
    });
});
