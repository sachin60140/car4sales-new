<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email', 'email_verified_at', 'branch_id', 'department_id', 'team_id']),
                'roles' => $user?->getRoleNames() ?? [],
                'permissions' => $user === null ? [] : $this->permissionsFor($user),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'enquiry_success' => $request->session()->get('enquiry_success'),
            ],
            'site' => [
                'name' => config('car4sales.public.company_name'),
                'tagline' => config('car4sales.public.tagline'),
                'phone' => config('car4sales.public.phone'),
                'whatsapp' => config('car4sales.public.whatsapp'),
                'email' => config('car4sales.public.email'),
                'otp_required' => (bool) config('car4sales.public.require_otp'),
            ],
            'notifications' => fn () => $this->notificationsFor($user),
        ]);
    }

    /**
     * Unread count + a short recent list for the header bell. Lazily evaluated so
     * it only runs for authenticated requests.
     *
     * @return array{unread: int, recent: array<int, array<string, mixed>>}
     */
    private function notificationsFor($user): array
    {
        if ($user === null) {
            return ['unread' => 0, 'recent' => []];
        }

        $recent = \App\Domain\Notifications\Models\Notification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get(['id', 'type', 'level', 'title', 'body', 'action_url', 'read_at', 'created_at']);

        return [
            'unread' => \App\Domain\Notifications\Models\Notification::query()
                ->where('user_id', $user->id)->whereNull('read_at')->count(),
            'recent' => $recent->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'level' => $n->level->value,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /** @return list<string> */
    private function permissionsFor($user): array
    {
        if ($user->hasRole('Super Admin')) {
            return ['*'];
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
