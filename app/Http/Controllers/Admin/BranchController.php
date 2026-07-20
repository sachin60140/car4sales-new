<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->withCount('users')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/branches/Index', [
            'branches' => $branches,
            'filters' => ['search' => $request->string('search')->toString()],
            'can' => [
                'create' => $request->user()->can('create', Branch::class),
                'update' => $request->user()->can('branches.update'),
                'delete' => $request->user()->can('branches.delete'),
            ],
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::query()->create($request->payload());

        return back()->with('success', 'Branch created.');
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->payload());

        return back()->with('success', 'Branch updated.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->users()->exists()) {
            return back()->withErrors(['branch' => 'Cannot delete a branch that still has employees.']);
        }

        $branch->delete();

        return back()->with('success', 'Branch deleted.');
    }
}
