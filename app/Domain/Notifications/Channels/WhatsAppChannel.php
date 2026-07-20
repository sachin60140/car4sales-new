<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel extends NotificationChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    protected function destination(User $user): ?string
    {
        return $user->phone;
    }

    protected function transmit(Notification $notification, User $user, string $destination): string
    {
        // A real WhatsApp Business/Gupshup template send would go here.
        Log::channel('stack')->info('[WhatsApp] '.$destination.': '.$notification->title);

        return 'Logged for '.$destination;
    }
}
