<?php

namespace App\Listeners;

use App\Domain\Audit\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthEvent
{
    public function handleLogin(Login $event): void
    {
        $this->record($event->user, 'login', $event->guard);

        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user !== null) {
            $this->record($event->user, 'logout', $event->guard);
        }
    }

    public function handleFailed(Failed $event): void
    {
        LoginHistory::query()->create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->credentials['email'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => (string) str(request()->userAgent() ?? '')->limit(500),
            'guard' => $event->guard ?: 'web',
            'event' => 'failed',
        ]);
    }

    private function record(mixed $user, string $type, ?string $guard): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $user?->email ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => (string) str(request()->userAgent() ?? '')->limit(500),
            'guard' => $guard ?: 'web',
            'event' => $type,
        ]);
    }
}
