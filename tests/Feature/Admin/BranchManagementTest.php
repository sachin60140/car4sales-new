<?php

use App\Domain\Branches\Models\Branch;
use App\Models\User;

it('lists branches for a user with permission', function () {
    $user = userWithPermissions(['branches.view']);
    Branch::factory()->count(3)->create();

    $this->actingAs($user)
        ->get('/admin/branches')
        ->assertOk();
});

it('forbids branch listing without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/branches')
        ->assertForbidden();
});

it('creates a branch', function () {
    $user = userWithPermissions(['branches.view', 'branches.create']);

    $this->actingAs($user)
        ->post('/admin/branches', [
            'code' => 'BR01',
            'name' => 'North Branch',
            'city' => 'Delhi',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Branch::query()->where('code', 'BR01')->exists())->toBeTrue();
});

it('rejects duplicate branch codes', function () {
    $user = userWithPermissions(['branches.view', 'branches.create']);
    Branch::factory()->create(['code' => 'BR01']);

    $this->actingAs($user)
        ->from('/admin/branches')
        ->post('/admin/branches', ['code' => 'BR01', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('code');
});

it('updates a branch', function () {
    $user = userWithPermissions(['branches.view', 'branches.update']);
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->put("/admin/branches/{$branch->id}", [
            'code' => $branch->code,
            'name' => 'Renamed Branch',
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($branch->fresh())
        ->name->toBe('Renamed Branch')
        ->is_active->toBeFalse();
});

it('soft deletes a branch without employees', function () {
    $user = userWithPermissions(['branches.view', 'branches.delete']);
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->delete("/admin/branches/{$branch->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('branches', ['id' => $branch->id]);
});

it('blocks deleting a branch that has employees', function () {
    $user = userWithPermissions(['branches.view', 'branches.delete']);
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->from('/admin/branches')
        ->delete("/admin/branches/{$branch->id}")
        ->assertSessionHasErrors('branch');

    expect(Branch::query()->find($branch->id))->not->toBeNull();
});

it('records activity when a branch changes', function () {
    $user = userWithPermissions(['branches.view', 'branches.create']);

    $this->actingAs($user)->post('/admin/branches', [
        'code' => 'AUD1',
        'name' => 'Audited Branch',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'branch',
        'event' => 'created',
        'causer_id' => $user->id,
    ]);
});
