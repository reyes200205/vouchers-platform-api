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
        $role = \Spatie\Permission\Models\Role::findOrCreate('cashier', 'web');

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
            'role' => $role->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'employee_code' => 'EMP-777',
                    'position' => 'Cashier',
                    'status' => 'active',
                    'user' => [
                        'email' => 'employee.test@example.com',
                    ],
                    'person' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                    ],
                    'branch' => [
                        'id' => $branch->id,
                        'name' => 'Branch Office',
                    ],
                ],
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
        $role = \Spatie\Permission\Models\Role::findOrCreate('cashier', 'web');

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

        $userCountBefore = User::query()->count();
        $personCountBefore = Person::query()->count();
        $employeeCountBefore = Employee::query()->count();

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
                'role' => $role->id,
            ]);
            $this->fail('Service did not throw exception on duplicate email');
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected exception due to database unique constraint
        }

        // Verify that no new User, Person or Employee was committed to the DB
        expect(User::query()->count())->toBe($userCountBefore);
        expect(Person::query()->count())->toBe($personCountBefore);
        expect(Employee::query()->count())->toBe($employeeCountBefore);
    });
});

describe('Employee Listing', function (): void {
    it('allows general_manager to list all employees across all branches', function (): void {
        $gmRole = \Spatie\Permission\Models\Role::findOrCreate('general_manager', 'web');
        $authUser = User::factory()->create();
        $authUser->assignRole($gmRole);
        Sanctum::actingAs($authUser);

        $address = Address::create([
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $branch1 = Branch::create([
            'name' => 'Branch Office 1',
            'branch_code' => 'BR-1',
            'branch_type' => 'main_office',
            'address_id' => $address->id,
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch Office 2',
            'branch_code' => 'BR-2',
            'branch_type' => 'subsidiary_office',
            'address_id' => $address->id,
        ]);

        // Employee in Branch 1
        $user1 = User::factory()->create(['email' => 'emp1@example.com']);
        $person1 = Person::create([
            'first_name' => 'Emp',
            'last_name' => 'One',
            'full_name' => 'Emp One',
            'birth_date' => '1995-05-15',
            'gender' => 'male',
        ]);
        Employee::create([
            'user_id' => $user1->id,
            'person_id' => $person1->id,
            'branch_id' => $branch1->id,
            'employee_code' => 'EMP-001',
            'position' => 'Developer',
            'status' => 'active',
        ]);

        // Employee in Branch 2
        $user2 = User::factory()->create(['email' => 'emp2@example.com']);
        $person2 = Person::create([
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'full_name' => 'Emp Two',
            'birth_date' => '1995-05-15',
            'gender' => 'female',
        ]);
        Employee::create([
            'user_id' => $user2->id,
            'person_id' => $person2->id,
            'branch_id' => $branch2->id,
            'employee_code' => 'EMP-002',
            'position' => 'Designer',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        expect(count($data))->toBe(2);
    });

    it('restricts branch_manager to only list employees from their own branch', function (): void {
        $bmRole = \Spatie\Permission\Models\Role::findOrCreate('branch_manager', 'web');
        $authUser = User::factory()->create();
        $authUser->assignRole($bmRole);
        Sanctum::actingAs($authUser);

        $address = Address::create([
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123',
            'postal_code' => '64000',
        ]);

        $branch1 = Branch::create([
            'name' => 'Branch Office 1',
            'branch_code' => 'BR-1',
            'branch_type' => 'main_office',
            'address_id' => $address->id,
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch Office 2',
            'branch_code' => 'BR-2',
            'branch_type' => 'subsidiary_office',
            'address_id' => $address->id,
        ]);

        // Let the authenticated user act as an employee of Branch 2
        $authPerson = Person::create([
            'first_name' => 'Manager',
            'last_name' => 'Two',
            'full_name' => 'Manager Two',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ]);
        Employee::create([
            'user_id' => $authUser->id,
            'person_id' => $authPerson->id,
            'branch_id' => $branch2->id,
            'employee_code' => 'MGR-002',
            'position' => 'Branch Manager',
            'status' => 'active',
        ]);

        // Employee in Branch 1
        $user1 = User::factory()->create(['email' => 'emp1@example.com']);
        $person1 = Person::create([
            'first_name' => 'Emp',
            'last_name' => 'One',
            'full_name' => 'Emp One',
            'birth_date' => '1995-05-15',
            'gender' => 'male',
        ]);
        Employee::create([
            'user_id' => $user1->id,
            'person_id' => $person1->id,
            'branch_id' => $branch1->id,
            'employee_code' => 'EMP-001',
            'position' => 'Developer',
            'status' => 'active',
        ]);

        // Employee in Branch 2 (same branch as manager)
        $user2 = User::factory()->create(['email' => 'emp2@example.com']);
        $person2 = Person::create([
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'full_name' => 'Emp Two',
            'birth_date' => '1995-05-15',
            'gender' => 'female',
        ]);
        Employee::create([
            'user_id' => $user2->id,
            'person_id' => $person2->id,
            'branch_id' => $branch2->id,
            'employee_code' => 'EMP-002',
            'position' => 'Designer',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        // Should only see the 2 employees of Branch 2 (the manager themselves and the designer), and NOT the developer in Branch 1
        expect(count($data))->toBe(2);

        $employeeCodes = collect($data)->pluck('employee_code')->toArray();
        expect($employeeCodes)->toContain('MGR-002');
        expect($employeeCodes)->toContain('EMP-002');
        expect($employeeCodes)->not->toContain('EMP-001');
    });
});

