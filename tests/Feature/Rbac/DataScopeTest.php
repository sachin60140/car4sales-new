<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\RolesPermissions\Enums\DataScope;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\Teams\Models\Team;
use App\Models\User;

it('resolves the widest scope across roles', function () {
    $user = userWithPermissions(['employees.view'], scope: 'own_branch');

    expect(app(ScopeService::class)->scopeFor($user))->toBe(DataScope::OwnBranch);
});

it('limits own_branch users to their branch in employee listings', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $viewer = userWithPermissions(['employees.view'], scope: 'own_branch', attributes: ['branch_id' => $branchA->id]);
    User::factory()->count(3)->create(['branch_id' => $branchA->id]);
    User::factory()->count(2)->create(['branch_id' => $branchB->id]);

    $visible = app(ScopeService::class)
        ->applyToUsers(User::query(), $viewer)
        ->pluck('branch_id')
        ->unique();

    expect($visible->all())->toBe([$branchA->id]);
});

it('limits own_team users to their team members', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $viewer = userWithPermissions(['employees.view'], scope: 'own_team', attributes: ['team_id' => $team->id]);
    User::factory()->count(2)->create(['team_id' => $team->id]);
    User::factory()->count(4)->create(['team_id' => $otherTeam->id]);

    $count = app(ScopeService::class)->applyToUsers(User::query(), $viewer)->count();

    // Viewer + 2 teammates.
    expect($count)->toBe(3);
});

it('restricts assigned scope to the user themselves in user listings', function () {
    $viewer = userWithPermissions(['employees.view'], scope: 'assigned');
    User::factory()->count(5)->create();

    $ids = app(ScopeService::class)->applyToUsers(User::query(), $viewer)->pluck('id');

    expect($ids->all())->toBe([$viewer->id]);
});

it('grants everything to super admins through the gate bypass', function () {
    $admin = superAdmin();

    expect($admin->can('branches.delete'))->toBeTrue()
        ->and($admin->can('anything.at-all'))->toBeTrue();
});

it('denies permissions that were not granted', function () {
    $user = userWithPermissions(['branches.view']);

    expect($user->can('branches.view'))->toBeTrue()
        ->and($user->can('branches.delete'))->toBeFalse();
});
