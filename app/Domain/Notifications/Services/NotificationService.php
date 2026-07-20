<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Channels\MailChannel;
use App\Domain\Notifications\Channels\NotificationChannel;
use App\Domain\Notifications\Channels\PushChannel;
use App\Domain\Notifications\Channels\SmsChannel;
use App\Domain\Notifications\Channels\WhatsAppChannel;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Jobs\DeliverNotification;
use App\Domain\Notifications\Models\Notification;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Central dispatcher for in-app + outbound notifications.
 *
 * Every notification is persisted (the always-on "database" channel powers the
 * in-app inbox). Enabled outbound channels then fan out through their drivers,
 * each recording a delivery row. Recipient helpers resolve users by permission
 * or role so callers can target "the assignee" or "branch managers" cleanly.
 */
class NotificationService
{
    /** When muted, notify() is a no-op — used to keep demo seeding side-effect free. */
    private static bool $muted = false;

    /** @var array<string, NotificationChannel> */
    private array $channels;

    public static function mute(): void
    {
        self::$muted = true;
    }

    public static function unmute(): void
    {
        self::$muted = false;
    }

    public function __construct(MailChannel $mail, SmsChannel $sms, WhatsAppChannel $whatsapp, PushChannel $push)
    {
        $this->channels = [
            'mail' => $mail,
            'sms' => $sms,
            'whatsapp' => $whatsapp,
            'push' => $push,
        ];
    }

    /**
     * Send a notification to a single user.
     *
     * @param  array{level?: NotificationLevel, body?: string, action_url?: string, data?: array<string, mixed>, branch_id?: int|null, channels?: array<int, string>}  $options
     */
    public function notify(User $user, string $type, string $title, array $options = []): ?Notification
    {
        if (self::$muted || ! $user->is_active) {
            return null;
        }

        $level = $options['level'] ?? NotificationLevel::Info;

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'level' => $level->value,
            'title' => $title,
            'body' => $options['body'] ?? null,
            'action_url' => $options['action_url'] ?? null,
            'data' => $options['data'] ?? null,
            'branch_id' => $options['branch_id'] ?? $user->branch_id,
        ]);

        // The in-app inbox is the always-on "database" channel — written now so
        // the notification is instantly visible in the bell/inbox.
        $notification->deliveries()->create([
            'channel' => 'database', 'driver' => 'database', 'status' => 'sent', 'sent_at' => now(),
        ]);

        // Fan the slow, network-bound channels out to a queued job so they never
        // hold the caller's transaction open. Only enabled channels are queued.
        $outbound = array_values(array_filter(
            $this->resolveChannels($options['channels'] ?? null),
            fn (string $key) => isset($this->channels[$key]) && $this->channels[$key]->isEnabled(),
        ));

        if ($outbound !== []) {
            DeliverNotification::dispatch($notification->id, $outbound);
        }

        return $notification;
    }

    /**
     * Run the outbound channel fan-out for a persisted notification. Called by the
     * DeliverNotification job (and reusable for retries).
     *
     * @param  array<int, string>  $channelKeys
     */
    public function dispatchChannels(Notification $notification, array $channelKeys): void
    {
        $user = $notification->user;
        if ($user === null) {
            return;
        }

        foreach ($channelKeys as $key) {
            $channel = $this->channels[$key] ?? null;
            if ($channel !== null && $channel->isEnabled()) {
                $channel->deliver($notification, $user);
            }
        }
    }

    /**
     * Send the same notification to many users, de-duplicated by id.
     *
     * @param  iterable<int, User|null>  $users
     * @param  array<string, mixed>  $options
     */
    public function notifyMany(iterable $users, string $type, string $title, array $options = []): int
    {
        $seen = [];
        $count = 0;

        foreach ($users as $user) {
            if ($user === null || in_array($user->id, $seen, true)) {
                continue;
            }
            $seen[] = $user->id;

            if ($this->notify($user, $type, $title, $options) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Active users holding a permission, optionally limited to a branch
     * (head-office / all-scope users are always included).
     *
     * @return Collection<int, User>
     */
    public function usersWithPermission(string $permission, ?int $branchId = null): Collection
    {
        // The Spatie scope throws if the permission does not exist yet.
        if (! Permission::query()->where('name', $permission)->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->permission($permission)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get();
    }

    /**
     * Active users holding any of the given roles, optionally limited to a branch.
     *
     * @param  string|array<int, string>  $roles
     * @return Collection<int, User>
     */
    public function usersWithRole(string|array $roles, ?int $branchId = null): Collection
    {
        // Filter to roles that actually exist — the Spatie scope throws otherwise.
        $names = Role::query()
            ->whereIn('name', (array) $roles)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        if ($names === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->role($names)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get();
    }

    /**
     * @param  array<int, string>|null  $requested
     * @return array<int, string>
     */
    private function resolveChannels(?array $requested): array
    {
        if ($requested !== null) {
            return $requested;
        }

        // Default: every channel enabled in config.
        return array_keys(array_filter((array) config('car4sales.notifications.channels', [])));
    }
}
