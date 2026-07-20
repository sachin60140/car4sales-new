<?php

use App\Domain\Notifications\Models\Notification;
use App\Models\User;

function demoNotification(User $user, array $overrides = []): Notification
{
    return Notification::query()->create(array_merge([
        'user_id' => $user->id,
        'type' => 'test.event',
        'level' => 'info',
        'title' => 'Ping',
        'body' => 'Body',
    ], $overrides));
}

it('renders the inbox with the unread count', function () {
    $user = userWithPermissions([]);
    demoNotification($user);
    demoNotification($user, ['read_at' => now()]);

    $this->actingAs($user)->get('/admin/notifications')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/notifications/Index')->where('unread', 1)->has('notifications.data', 2));
});

it('marks a single notification read', function () {
    $user = userWithPermissions([]);
    $n = demoNotification($user);

    $this->actingAs($user)->post("/admin/notifications/{$n->id}/read")->assertRedirect();

    expect($n->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications read', function () {
    $user = userWithPermissions([]);
    demoNotification($user);
    demoNotification($user);

    $this->actingAs($user)->post('/admin/notifications/read-all')->assertRedirect();

    expect(Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0);
});

it('forbids marking another user\'s notification read', function () {
    $owner = userWithPermissions([]);
    $other = userWithPermissions([]);
    $n = demoNotification($owner);

    $this->actingAs($other)->post("/admin/notifications/{$n->id}/read")->assertForbidden();
    expect($n->fresh()->read_at)->toBeNull();
});

it('lists notifications through the mobile api with the unread meta', function () {
    $user = userWithPermissions(['access-mobile']);
    demoNotification($user);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Test',
    ])->json('data.token');

    $this->getJson('/api/v1/notifications', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonStructure(['data', 'meta' => ['unread', 'pagination']]);
});

it('marks a notification read through the mobile api', function () {
    $user = userWithPermissions(['access-mobile']);
    $n = demoNotification($user);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Test',
    ])->json('data.token');

    $this->postJson("/api/v1/notifications/{$n->id}/read", [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.read', true);

    expect($n->fresh()->read_at)->not->toBeNull();
});
