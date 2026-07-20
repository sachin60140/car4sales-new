<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushChannel extends NotificationChannel
{
    public function key(): string
    {
        return 'push';
    }

    protected function destination(User $user): ?string
    {
        $tokens = $user->devices()
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();

        return $tokens === [] ? null : implode(',', $tokens);
    }

    protected function transmit(Notification $notification, User $user, string $destination): string
    {
        // A real FCM/APNs multicast send would go here, using the device tokens.
        $count = count(explode(',', $destination));
        Log::channel('stack')->info('[Push] '.$count.' device(s) for '.$user->name.': '.$notification->title);

        return 'Logged for '.$count.' device(s)';
    }
}
