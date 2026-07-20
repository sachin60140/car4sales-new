<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\LoginHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function activity(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $logs = Activity::query()
            ->with('causer:id,name')
            ->when($request->string('log')->toString(), fn ($q, $log) => $q->where('log_name', $log))
            ->when($request->string('event')->toString(), fn ($q, $event) => $q->where('event', $event))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/audit/Activity', [
            'logs' => $logs,
            'filters' => [
                'log' => $request->string('log')->toString() ?: null,
                'event' => $request->string('event')->toString() ?: null,
            ],
        ]);
    }

    public function logins(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $logins = LoginHistory::query()
            ->with('user:id,name,email')
            ->when($request->string('event')->toString(), fn ($q, $event) => $q->where('event', $event))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/audit/Logins', [
            'logins' => $logins,
            'filters' => ['event' => $request->string('event')->toString() ?: null],
        ]);
    }
}
