<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SmsChannel extends NotificationChannel
{
    public function key(): string
    {
        return 'sms';
    }

    protected function destination(User $user): ?string
    {
        return $user->phone;
    }

    protected function transmit(Notification $notification, User $user, string $destination): string
    {
        // A real provider (MSG91/Gupshup/Twilio) would post here. The log driver
        // records the attempt so the flow is verifiable without credentials.
        Log::channel('stack')->info('[SMS] '.$destination.': '.$notification->title);

        return 'Logged for '.$destination;
    }
}
