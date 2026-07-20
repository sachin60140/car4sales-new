<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->withCount('users')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/departments/Index', [
            'departments' => $departments,
            'filters' => ['search' => $request->string('search')->toString()],
            'can' => [
                'create' => $request->user()->can('create', Department::class),
                'update' => $request->user()->can('departments.update'),
                'delete' => $request->user()->can('departments.delete'),
            ],
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->payload());

        return back()->with('success', 'Department created.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->payload());

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if ($department->users()->exists() || $department->teams()->exists()) {
            return back()->withErrors(['department' => 'Cannot delete a department that is still in use.']);
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
