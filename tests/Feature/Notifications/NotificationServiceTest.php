<?php

use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Jobs\DeliverNotification;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('car4sales.notifications.channels', ['mail' => true, 'sms' => true, 'whatsapp' => false, 'push' => true]);
    config()->set('car4sales.notifications.drivers', ['mail' => 'array', 'sms' => 'log', 'whatsapp' => 'log', 'push' => 'log']);
});

it('persists a notification and fans out to the enabled channels', function () {
    $user = userWithPermissions([], attributes: ['phone' => '9990001111']);

    $notification = app(NotificationService::class)->notify($user, 'test.event', 'Hello there', [
        'body' => 'A body',
        'level' => NotificationLevel::Success,
        'action_url' => '/admin/dashboard',
    ]);

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('Hello there')
        ->and($notification->level)->toBe(NotificationLevel::Success)
        ->and($notification->read_at)->toBeNull();

    $deliveries = $notification->deliveries()->pluck('status', 'channel');
    expect($deliveries)->toHaveKeys(['database', 'mail', 'sms'])
        ->and($deliveries->has('whatsapp'))->toBeFalse() // channel disabled
        ->and($deliveries['database'])->toBe('sent')
        ->and($deliveries['sms'])->toBe('sent');
});

it('skips a channel that has no destination for the user', function () {
    $user = userWithPermissions([], attributes: ['phone' => null]); // no phone → sms/whatsapp skipped

    $notification = app(NotificationService::class)->notify($user, 'test.event', 'No phone');

    expect($notification->deliveries()->where('channel', 'sms')->value('status'))->toBe('skipped');
});

it('never notifies an inactive user', function () {
    $user = userWithPermissions([], attributes: ['is_active' => false]);

    expect(app(NotificationService::class)->notify($user, 'test.event', 'Nope'))->toBeNull();
});

it('is a no-op while muted', function () {
    $user = userWithPermissions([]);

    NotificationService::mute();
    $result = app(NotificationService::class)->notify($user, 'test.event', 'Muted');
    NotificationService::unmute();

    expect($result)->toBeNull()
        ->and(\App\Domain\Notifications\Models\Notification::count())->toBe(0);
});

it('resolves recipients by role and de-duplicates', function () {
    $role = Role::query()->create(['name' => 'Branch Manager', 'guard_name' => 'web']);
    $a = User::factory()->create(['is_active' => true]);
    $b = User::factory()->create(['is_active' => true]);
    $a->assignRole($role);
    $b->assignRole($role);

    $service = app(NotificationService::class);
    $recipients = $service->usersWithRole('Branch Manager');

    expect($recipients)->toHaveCount(2);

    // Passing the same user twice only produces one notification.
    $sent = $service->notifyMany([$a, $a, $b], 'test.event', 'Team ping');
    expect($sent)->toBe(2);
});

it('returns no recipients for an unknown role rather than throwing', function () {
    expect(app(NotificationService::class)->usersWithRole('Nonexistent Role'))->toHaveCount(0);
});

it('writes the inbox row synchronously but queues the outbound fan-out', function () {
    Queue::fake();
    $user = userWithPermissions([], attributes: ['phone' => '9990001111']);

    $notification = app(NotificationService::class)->notify($user, 'test.event', 'Queued');

    // The in-app row + its database delivery exist immediately.
    expect($notification->deliveries()->where('channel', 'database')->exists())->toBeTrue()
        // Outbound channels are deferred to the job, not run inline.
        ->and($notification->deliveries()->where('channel', 'mail')->exists())->toBeFalse();

    Queue::assertPushed(DeliverNotification::class, fn ($job) => $job->notificationId === $notification->id
        && in_array('mail', $job->channelKeys, true));
});

it('does not queue a job when no outbound channel is enabled', function () {
    config()->set('car4sales.notifications.channels', ['mail' => false, 'sms' => false, 'whatsapp' => false, 'push' => false]);
    Queue::fake();

    app(NotificationService::class)->notify(userWithPermissions([]), 'test.event', 'Inbox only');

    Queue::assertNothingPushed();
});

it('truncates oversized push destinations instead of overflowing the audit column', function () {
    $user = userWithPermissions([]);

    // Three devices with realistic long FCM tokens (comma-joined > 1200 chars).
    foreach (range(1, 3) as $i) {
        $user->devices()->create([
            'device_uuid' => "qa-device-{$i}",
            'device_name' => 'QA Phone',
            'fcm_token' => str_repeat("tok{$i}_", 80), // 400+ chars each
        ]);
    }

    $notification = app(NotificationService::class)->notify($user, 'test.event', 'Push fanout');

    $delivery = $notification->deliveries()->where('channel', 'push')->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('sent')
        ->and(mb_strlen($delivery->destination))->toBeLessThanOrEqual(255);
});
