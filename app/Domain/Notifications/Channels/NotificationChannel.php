<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Models\User;
use Throwable;

/**
 * A delivery channel for outbound notifications (mail, sms, whatsapp, push).
 *
 * The channel resolves the recipient's destination and hands off to its
 * configured driver. The "log" / "null" drivers make the whole fan-out run
 * without a live provider (XAMPP-friendly) while still recording an auditable
 * delivery row per attempt.
 */
abstract class NotificationChannel
{
    /** Channel key: mail|sms|whatsapp|push. */
    abstract public function key(): string;

    /** Where this channel would send for the given user (email, phone, token…). */
    abstract protected function destination(User $user): ?string;

    /** Perform the actual send; return a short provider response string. */
    abstract protected function transmit(Notification $notification, User $user, string $destination): string;

    public function driver(): string
    {
        return (string) config("car4sales.notifications.drivers.{$this->key()}", 'log');
    }

    public function isEnabled(): bool
    {
        return (bool) config("car4sales.notifications.channels.{$this->key()}", false);
    }

    /**
     * Deliver the notification over this channel, recording the outcome.
     */
    public function deliver(Notification $notification, User $user): NotificationDelivery
    {
        $destination = $this->destination($user);

        if ($destination === null || $destination === '') {
            return $this->record($notification, 'skipped', null, 'No destination for channel.');
        }

        if ($this->driver() === 'null') {
            return $this->record($notification, 'skipped', $destination, 'Channel driver disabled.');
        }

        try {
            $response = $this->transmit($notification, $user, $destination);

            return $this->record($notification, 'sent', $destination, $response);
        } catch (Throwable $e) {
            return $this->record($notification, 'failed', $destination, $e->getMessage());
        }
    }

    protected function record(Notification $notification, string $status, ?string $destination, ?string $response): NotificationDelivery
    {
        return $notification->deliveries()->create([
            'channel' => $this->key(),
            'driver' => $this->driver(),
            'status' => $status,
            // The column is VARCHAR(255); push destinations can be comma-joined
            // FCM tokens (512 chars each), so truncate defensively — an audit-row
            // overflow must never abort the business transaction that notified.
            'destination' => $destination === null ? null : mb_substr($destination, 0, 255),
            'response' => $response,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
