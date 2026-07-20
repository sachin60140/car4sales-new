<?php

use App\Domain\Notifications\Models\Notification;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;

it('sends a daily digest to managers', function () {
    Role::query()->create(['name' => 'Branch Manager', 'guard_name' => 'web']);
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Branch Manager');

    $this->artisan('reports:daily-digest')->assertSuccessful();

    expect(Notification::query()
        ->where('type', 'digest.daily')
        ->where('user_id', $manager->id)
        ->exists())->toBeTrue();
});

it('reports gracefully when there are no managers', function () {
    $this->artisan('reports:daily-digest')
        ->expectsOutputToContain('No digest recipients found.')
        ->assertSuccessful();
});
