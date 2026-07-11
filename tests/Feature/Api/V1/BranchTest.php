<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Branch Creation', function (): void {
    it('creates a branch successfully with valid flat address and manager', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $person = Person::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'employee_code' => 'EMP-001',
            'position' => 'Manager',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Branch Test Office',
            'branch_code' => 'BR-001',
            'branch_type' => 'main_office',
            'manager_id' => $employee->id,
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Success',
            ]);

        $this->assertDatabaseHas('addresses', [
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $this->assertDatabaseHas('branches', [
            'name' => 'Branch Test Office',
            'branch_code' => 'BR-001',
            'branch_type' => 'main_office',
            'manager_id' => $employee->id,
        ]);
    });

    it('creates a branch successfully without branch_code and manager_id (auto-generates code)', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Branch Auto-Gen Office',
            'branch_type' => 'subsidiary_office',
            'country' => 'Mexico',
            'state' => 'Coahuila',
            'city' => 'Torreon',
            'address' => 'Av Guadalupana calle educacion 110',
            'postal_code' => '27108',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Success',
            ]);

        $this->assertDatabaseHas('addresses', [
            'country' => 'Mexico',
            'state' => 'Coahuila',
            'city' => 'Torreon',
            'address' => 'Av Guadalupana calle educacion 110',
            'postal_code' => '27108',
        ]);

        // Verify the branch was created in DB and has an auto-generated code starting with BR-
        $this->assertDatabaseHas('branches', [
            'name' => 'Branch Auto-Gen Office',
            'branch_type' => 'subsidiary_office',
            'manager_id' => null,
        ]);

        $branch = \App\Models\Branch::where('name', 'Branch Auto-Gen Office')->first();
        expect($branch->branch_code)->toStartWith('BR-');
    });

    it('fails validation when address fields are missing', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Branch Test Office',
            'branch_code' => 'BR-001',
            'branch_type' => 'main_office',
        ]);

        $response->assertStatus(422);
    });
});
