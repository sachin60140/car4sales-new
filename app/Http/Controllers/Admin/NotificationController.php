<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\Models\Notification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($request->string('filter')->toString() === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'level' => $n->level->value,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/notifications/Index', [
            'notifications' => $notifications,
            'unread' => Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
            'filter' => $request->string('filter')->toString() ?: 'all',
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
