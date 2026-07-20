<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the outbound channel fan-out (mail / SMS / WhatsApp / push) off the
 * request path. The in-app "database" channel is written synchronously by
 * NotificationService; only the slow, network-bound channels are queued so they
 * never hold a domain transaction open. On the sync queue (local/test) this
 * runs inline, preserving synchronous behaviour.
 */
class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $channelKeys  outbound channels to attempt
     */
    public function __construct(
        public readonly int $notificationId,
        public readonly array $channelKeys,
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $notification = Notification::query()->with('user')->find($this->notificationId);

        // The notification may have been removed (e.g. its owner deleted, or the
        // creating transaction rolled back). Nothing to deliver.
        if ($notification === null || $notification->user === null) {
            return;
        }

        $notifications->dispatchChannels($notification, $this->channelKeys);
    }
}
