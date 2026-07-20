<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopes,
        private readonly NumberSequenceService $sequences,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $employees = User::query()
            ->with(['branch:id,name', 'department:id,name', 'team:id,name', 'roles:id,name', 'employeeProfile:id,user_id,employee_code,designation'])
            ->tap(fn ($query) => $this->scopes->applyToUsers($query, $request->user()))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/Index', [
            'employees' => $employees,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('sort_order')->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->integer('branch_id') ?: null,
                'department_id' => $request->integer('department_id') ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ],
            'can' => [
                'create' => $request->user()->can('create', User::class),
                'update' => $request->user()->can('employees.update'),
                'delete' => $request->user()->can('employees.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/employees/Form', $this->formProps($request));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'team_id' => $data['team_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'force_password_change' => $data['force_password_change'] ?? false,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($data['roles']);

            $user->employeeProfile()->create([
                ...($data['profile'] ?? []),
                'employee_code' => $this->sequences->next('employee'),
            ]);
        });

        return redirect()->route('admin.employees.index')->with('success', 'Employee created.');
    }

    public function edit(Request $request, User $employee): Response
    {
        $this->authorize('update', $employee);

        $employee->load(['roles:id,name', 'employeeProfile']);

        return Inertia::render('admin/employees/Form', [
            ...$this->formProps($request),
            'employee' => $employee,
        ]);
    }

    public function update(EmployeeRequest $request, User $employee): RedirectResponse
    {
        DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();

            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'team_id' => $data['team_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'force_password_change' => $data['force_password_change'] ?? false,
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
                $attributes['password_changed_at'] = now();
            }

            $employee->update($attributes);
            $employee->syncRoles($data['roles']);

            if (isset($data['profile'])) {
                $employee->employeeProfile()->updateOrCreate(
                    ['user_id' => $employee->id],
                    $data['profile'],
                );
            }

            // Deactivation forces logout everywhere: revoke API tokens and devices.
            if (! $employee->is_active) {
                $employee->tokens()->delete();
                $employee->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
        });

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        DB::transaction(function () use ($employee) {
            $employee->tokens()->delete();
            $employee->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $employee->update(['is_active' => false]);
            $employee->delete();
        });

        return back()->with('success', 'Employee removed.');
    }

    private function formProps(Request $request): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'branch_id', 'department_id']),
            'roles' => Role::query()
                ->when(! $request->user()->hasRole('Super Admin'), fn ($q) => $q->where('name', '!=', 'Super Admin'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
