<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class MailChannel extends NotificationChannel
{
    public function key(): string
    {
        return 'mail';
    }

    protected function destination(User $user): ?string
    {
        return $user->email;
    }

    protected function transmit(Notification $notification, User $user, string $destination): string
    {
        Mail::mailer($this->driver())->to($destination)->send(new NotificationMail($notification));

        return 'Dispatched via '.$this->driver().' mailer.';
    }
}
