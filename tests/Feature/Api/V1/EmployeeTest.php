<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Employee Creation', function (): void {
    it('creates an employee successfully and registers User, Person and Employee in a transaction', function (): void {
        \Spatie\Permission\Models\Role::findOrCreate('cashier', 'web');

        $authUser = User::factory()->create();
        Sanctum::actingAs($authUser);

        $address = Address::create([
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $branch = Branch::create([
            'name' => 'Branch Office',
            'branch_code' => 'BR-123456',
            'branch_type' => 'main_office',
            'address_id' => $address->id,
        ]);

        $response = $this->postJson('/api/v1/employees', [
            'email' => 'employee.test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birth_date' => '1995-05-15',
            'gender' => 'male',
            'branch_id' => $branch->id,
            'employee_code' => 'EMP-777',
            'position' => 'Cashier',
            'status' => 'active',
            'role' => 'cashier',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Success',
            ]);

        // Verify User was created
        $this->assertDatabaseHas('users', [
            'email' => 'employee.test@example.com',
            'name' => 'John Doe',
        ]);

        // Verify that the user has the role assigned
        $user = User::where('email', 'employee.test@example.com')->first();
        expect($user->hasRole('cashier'))->toBeTrue();

        // Verify Person was created
        $this->assertDatabaseHas('people', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'birth_date' => '1995-05-15',
            'gender' => 'male',
        ]);

        // Verify Employee was created and references User and Person
        $person = Person::where('full_name', 'John Doe')->first();

        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'person_id' => $person->id,
            'branch_id' => $branch->id,
            'employee_code' => 'EMP-777',
            'position' => 'Cashier',
            'status' => 'active',
        ]);
    });

    it('rolls back database modifications if any creation step fails', function (): void {
        \Spatie\Permission\Models\Role::findOrCreate('cashier', 'web');

        $authUser = User::factory()->create();
        Sanctum::actingAs($authUser);

        $address = Address::create([
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $branch = Branch::create([
            'name' => 'Branch Office',
            'branch_code' => 'BR-123456',
            'branch_type' => 'main_office',
            'address_id' => $address->id,
        ]);

        // Create a duplicate user beforehand to trigger a DB unique constraint on 'users.email'
        User::create([
            'name' => 'Duplicate Email',
            'email' => 'duplicate@example.com',
            'password' => 'password',
        ]);

        $userCountBefore = User::count();
        $personCountBefore = Person::count();
        $employeeCountBefore = Employee::count();

        $service = new \App\Services\Employees\StoreEmployeeService();

        try {
            $service->execute([
                'email' => 'duplicate@example.com', // Duplicate email will fail User::create due to DB constraint
                'first_name' => 'Rolled',
                'last_name' => 'Back',
                'birth_date' => '1990-01-01',
                'gender' => 'other',
                'branch_id' => $branch->id,
                'employee_code' => 'EMP-FAIL',
                'position' => 'Cashier',
                'status' => 'active',
                'role' => 'cashier',
            ]);
            $this->fail('Service did not throw exception on duplicate email');
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected exception due to database unique constraint
        }

        // Verify that no new User, Person or Employee was committed to the DB
        expect(User::count())->toBe($userCountBefore);
        expect(Person::count())->toBe($personCountBefore);
        expect(Employee::count())->toBe($employeeCountBefore);
    });
});
