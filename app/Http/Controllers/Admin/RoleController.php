<?php

namespace App\Http\Controllers\Admin;

use App\Domain\RolesPermissions\Enums\DataScope;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RolesPermissions\Support\PermissionRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with('meta')
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles,
            'can' => [
                'create' => $request->user()->can('create', Role::class),
                'update' => $request->user()->can('roles.update'),
                'delete' => $request->user()->can('roles.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'data_scope' => ['required', Rule::enum(DataScope::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::query()->create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->meta()->create([
            'data_scope' => $data['data_scope'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role created.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('meta');

        return Inertia::render('admin/roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'data_scope' => $role->meta?->data_scope?->value ?? 'own',
                'description' => $role->meta?->description,
                'is_system' => (bool) ($role->meta?->is_system ?? false),
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
            'registry' => PermissionRegistry::modules(),
            'globalPermissions' => PermissionRegistry::global(),
            'scopes' => DataScope::options(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'data_scope' => ['required', Rule::enum(DataScope::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::all())],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $role->meta()->updateOrCreate(
            ['role_id' => $role->id],
            ['data_scope' => $data['data_scope'], 'description' => $data['description'] ?? null],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Cannot delete a role that is still assigned to users.']);
        }

        $role->meta()->delete();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
