<?php

namespace App\Http\Controllers\Admin;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadFollowup;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TelecallerReportController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view') && $request->user()->can('telecalling.view'), 403);

        $from = $request->date('from') ?? now()->subDays(29)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $leadScope = fn () => $this->scopes->apply(SalesLead::query(), $request->user(), ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by']);

        // Summary cards.
        $inWindow = (clone $leadScope())->whereBetween('created_at', [$from, $to]);
        $summary = [
            'total' => (clone $inWindow)->count(),
            'new' => (clone $inWindow)->where('status', SalesLeadStatus::New->value)->count(),
            'contacted' => (clone $inWindow)->whereNotNull('first_response_at')->count(),
            'unattended' => (clone $leadScope())->whereNull('first_response_at')
                ->whereIn('status', [SalesLeadStatus::New->value, SalesLeadStatus::Assigned->value])->count(),
            'interested' => (clone $inWindow)->where('status', SalesLeadStatus::Interested->value)->count(),
            'lost' => (clone $inWindow)->whereIn('status', [SalesLeadStatus::Lost->value, SalesLeadStatus::WrongNumber->value, SalesLeadStatus::Duplicate->value])->count(),
            'followups_due' => (clone $leadScope())->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now()->endOfDay())
                ->whereIn('status', SalesLeadStatus::openValues())->count(),
            'overdue' => (clone $leadScope())->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<', now()->startOfDay())
                ->whereIn('status', SalesLeadStatus::openValues())->count(),
        ];

        // Per-telecaller contribution.
        $telecallerRows = (clone $leadScope())
            ->whereNotNull('telecaller_id')
            ->whereBetween('created_at', [$from, $to])
            ->select('telecaller_id',
                DB::raw('COUNT(*) as assigned'),
                DB::raw('SUM(CASE WHEN first_response_at IS NOT NULL THEN 1 ELSE 0 END) as contacted'),
                DB::raw("SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested"),
                DB::raw("SUM(CASE WHEN status IN ('lost','wrong_number','duplicate') THEN 1 ELSE 0 END) as lost"))
            ->groupBy('telecaller_id')
            ->get();

        $names = User::query()->whereIn('id', $telecallerRows->pluck('telecaller_id'))->pluck('name', 'id');

        // Calls made per telecaller in window.
        $callStats = LeadFollowup::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('user_id', $telecallerRows->pluck('telecaller_id'))
            ->select('user_id',
                DB::raw('COUNT(*) as calls'),
                DB::raw("SUM(CASE WHEN call_outcome NOT IN ('no_answer','busy','switched_off','call_rejected') THEN 1 ELSE 0 END) as connected"))
            ->groupBy('user_id')->get()->keyBy('user_id');

        $telecallers = $telecallerRows->map(function ($row) use ($names, $callStats) {
            $calls = $callStats->get($row->telecaller_id);
            $assigned = (int) $row->assigned;

            return [
                'telecaller_id' => $row->telecaller_id,
                'name' => $names[$row->telecaller_id] ?? 'Unknown',
                'assigned' => $assigned,
                'contacted' => (int) $row->contacted,
                'calls' => (int) ($calls->calls ?? 0),
                'connected' => (int) ($calls->connected ?? 0),
                'interested' => (int) $row->interested,
                'lost' => (int) $row->lost,
                'conversion' => $assigned > 0 ? round(((int) $row->interested / $assigned) * 100, 1) : 0,
            ];
        })->sortByDesc('interested')->values();

        // Lead-source performance.
        $sources = (clone $leadScope())
            ->whereBetween('created_at', [$from, $to])
            ->select('source',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested"))
            ->groupBy('source')->orderByDesc('total')->get();

        // Call-outcome distribution.
        $outcomes = LeadFollowup::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('call_outcome')
            ->select('call_outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('call_outcome')->orderByDesc('total')->get();

        return Inertia::render('admin/reports/Telecaller', [
            'summary' => $summary,
            'telecallers' => $telecallers,
            'sources' => $sources,
            'outcomes' => $outcomes,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
