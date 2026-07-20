<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;

function employeePayload(array $overrides = []): array
{
    $branch = Branch::factory()->create();
    $department = Department::factory()->create();
    Role::query()->firstOrCreate(['name' => 'Telecaller', 'guard_name' => 'web']);

    return array_merge([
        'name' => 'Ravi Kumar',
        'email' => 'ravi@example.com',
        'password' => 'Secret@12345',
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
        'roles' => ['Telecaller'],
        'profile' => ['designation' => 'Telecaller'],
    ], $overrides);
}

it('creates an employee with profile, role and generated employee code', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->post('/admin/employees', employeePayload())
        ->assertRedirect('/admin/employees');

    $employee = User::query()->where('email', 'ravi@example.com')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->hasRole('Telecaller'))->toBeTrue()
        ->and($employee->employeeProfile->employee_code)->toStartWith('EMP-')
        ->and($employee->employeeProfile->designation)->toBe('Telecaller');
});

it('requires at least one role', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->from('/admin/employees/create')
        ->post('/admin/employees', employeePayload(['roles' => []]))
        ->assertSessionHasErrors('roles');
});

it('prevents non super admins from granting the super admin role', function () {
    $manager = userWithPermissions(['employees.view', 'employees.create']);
    Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->actingAs($manager)
        ->from('/admin/employees/create')
        ->post('/admin/employees', employeePayload(['roles' => ['Super Admin']]))
        ->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'ravi@example.com')->exists())->toBeFalse();
});

it('revokes api tokens and devices when an employee is deactivated', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->post('/admin/employees', employeePayload());
    $employee = User::query()->where('email', 'ravi@example.com')->first();

    $employee->createToken('device-1');
    $employee->devices()->create(['device_uuid' => 'device-1', 'platform' => 'android']);

    $this->actingAs($admin)->put("/admin/employees/{$employee->id}", employeePayload([
        'branch_id' => $employee->branch_id,
        'department_id' => $employee->department_id,
        'is_active' => false,
        'password' => '',
    ]));

    expect($employee->tokens()->count())->toBe(0)
        ->and($employee->devices()->whereNull('revoked_at')->count())->toBe(0)
        ->and($employee->fresh()->is_active)->toBeFalse();
});

it('blocks deactivated users from the panel', function () {
    $user = userWithPermissions(['branches.view'], attributes: ['is_active' => false]);

    $this->actingAs($user)
        ->get('/admin/branches')
        ->assertRedirect(route('login'));
});

it('soft deletes an employee and keeps history', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->post('/admin/employees', employeePayload());
    $employee = User::query()->where('email', 'ravi@example.com')->first();

    $this->actingAs($admin)
        ->delete("/admin/employees/{$employee->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('users', ['id' => $employee->id]);
});

it('does not allow deleting yourself', function () {
    $admin = userWithPermissions(['employees.view', 'employees.delete']);

    $this->actingAs($admin)
        ->delete("/admin/employees/{$admin->id}")
        ->assertForbidden();
});
