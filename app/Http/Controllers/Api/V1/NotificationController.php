<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\Models\Notification;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated(
            $notifications->through(fn (Notification $n) => $this->row($n)),
            meta: ['unread' => Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count()],
        );
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success(['id' => $notification->id, 'read' => true], 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(['marked' => $count], 'All notifications marked as read.');
    }

    private function row(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'level' => $n->level->value,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
