<?php

use App\Domain\RolesPermissions\Enums\DataScope;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user holding the Super Admin role (full bypass).
 */
function superAdmin(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $role->meta()->firstOrCreate([], ['data_scope' => DataScope::All, 'is_system' => true]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/**
 * Create a user granted the given permissions through a throwaway role.
 */
function userWithPermissions(array $permissions, string $scope = 'all', array $attributes = []): User
{
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role = Role::query()->create(['name' => 'Test Role '.uniqid(), 'guard_name' => 'web']);
    $role->meta()->create(['data_scope' => $scope, 'is_system' => false]);
    $role->syncPermissions($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}
