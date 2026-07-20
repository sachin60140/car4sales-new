<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::query()
            ->with(['branch:id,name', 'department:id,name', 'teamLeader:id,name'])
            ->withCount('members')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/teams/Index', [
            'teams' => $teams,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'leaders' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->integer('branch_id') ?: null,
            ],
            'can' => [
                'create' => $request->user()->can('create', Team::class),
                'update' => $request->user()->can('teams.update'),
                'delete' => $request->user()->can('teams.delete'),
            ],
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        Team::query()->create($request->validated());

        return back()->with('success', 'Team created.');
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return back()->with('success', 'Team updated.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if ($team->members()->exists()) {
            return back()->withErrors(['team' => 'Cannot delete a team that still has members.']);
        }

        $team->delete();

        return back()->with('success', 'Team deleted.');
    }
}
