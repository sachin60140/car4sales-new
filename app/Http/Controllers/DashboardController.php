<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Models\LoginHistory;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Branches\Models\Branch;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Departments\Models\Department;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'greeting' => [
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
                'branch' => $user->branch?->name,
            ],
            'stats' => $this->stats($user),
            'charts' => $this->charts($user),
            'recentLogins' => $user->can('audit.view')
                ? LoginHistory::query()->with('user:id,name,email')->latest('created_at')->limit(6)->get()
                : collect(),
        ]);
    }

    /**
     * Permission-gated, branch-scoped KPI tiles.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stats(User $user): array
    {
        $stats = [];

        if ($user->can('purchase-leads.view')) {
            $open = $this->scopedLeads($user)
                ->whereNotIn('status', ['purchased', 'closed', 'rejected', 'seller_not_interested', 'vehicle_sold_elsewhere'])
                ->count();

            $due = $this->scopedLeads($user)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now()->endOfDay())
                ->whereNotIn('status', ['purchased', 'closed', 'rejected', 'seller_not_interested', 'vehicle_sold_elsewhere'])
                ->count();

            $stats[] = ['key' => 'leads', 'label' => 'Active Leads', 'value' => $open, 'icon' => 'ShoppingCart', 'accent' => 'yellow', 'href' => '/admin/purchase-leads'];
            $stats[] = ['key' => 'followups', 'label' => 'Follow-ups Due', 'value' => $due, 'icon' => 'PhoneCall', 'accent' => 'orange', 'href' => '/admin/purchase-leads'];
        }

        if ($user->can('inspections.view')) {
            $pending = VehicleInspection::query()
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->when($user->hasRole('Inspector') && ! $user->hasAnyRole(['Super Admin', 'Purchase Manager', 'Branch Manager']),
                    fn ($q) => $q->where('inspector_id', $user->id))
                ->count();

            $stats[] = ['key' => 'inspections', 'label' => 'Inspections Pending', 'value' => $pending, 'icon' => 'ClipboardCheck', 'accent' => 'red', 'href' => '/admin/inspections'];
        }

        if ($user->can('bookings.view')) {
            $bookings = $this->scopes
                ->apply(Booking::query(), $user, ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by'])
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $stats[] = ['key' => 'bookings', 'label' => 'Bookings (30d)', 'value' => $bookings, 'icon' => 'BookMarked', 'accent' => 'yellow', 'href' => '/admin/bookings'];
        }

        if ($user->can('deliveries.view')) {
            $pendingDeliveries = $this->scopes
                ->apply(Delivery::query(), $user, ['branch' => 'branch_id', 'owner' => 'created_by'])
                ->whereIn('status', ['approval_pending', 'approved'])
                ->count();

            $stats[] = ['key' => 'deliveries', 'label' => 'Deliveries Pending', 'value' => $pendingDeliveries, 'icon' => 'Truck', 'accent' => 'orange', 'href' => '/admin/deliveries'];
        }

        if ($user->can('rto-cases.view')) {
            $rtoOpen = $this->scopes
                ->apply(RtoCase::query(), $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by'])
                ->where('status', '!=', RtoStatus::Closed->value)
                ->count();

            $stats[] = ['key' => 'rto', 'label' => 'RTO In Progress', 'value' => $rtoOpen, 'icon' => 'FileCheck2', 'accent' => 'maroon', 'href' => '/admin/rto-cases'];
        }

        if ($user->can('finance.view')) {
            $disbursed = $this->scopes
                ->apply(FinanceApplication::query(), $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by'])
                ->where('status', '!=', FinanceStatus::Rejected->value)
                ->whereIn('status', [FinanceStatus::Sanctioned->value, FinanceStatus::AgreementPending->value, FinanceStatus::DisbursementPending->value])
                ->count();

            $stats[] = ['key' => 'finance', 'label' => 'Finance To Disburse', 'value' => $disbursed, 'icon' => 'Wallet', 'accent' => 'gold', 'href' => '/admin/finance'];
        }

        if ($user->can('vehicles.view')) {
            $inStock = $this->scopes
                ->apply(Vehicle::query(), $user, ['branch' => 'branch_id', 'owner' => 'created_by'])
                ->whereIn('status', ['in_stock', 'inspection_pending', 'under_refurbishment', 'ready_for_sale', 'published'])
                ->count();

            $stats[] = ['key' => 'stock', 'label' => 'Vehicles in Stock', 'value' => $inStock, 'icon' => 'Car', 'accent' => 'maroon', 'href' => '#'];
        }

        if ($user->can('employees.view')) {
            $stats[] = [
                'key' => 'employees',
                'label' => 'Active Employees',
                'value' => $this->scopes->applyToUsers(User::query()->where('is_active', true), $user)->count(),
                'icon' => 'Users', 'accent' => 'gold', 'href' => '/admin/employees',
            ];
        }

        if ($user->can('branches.view')) {
            $stats[] = ['key' => 'branches', 'label' => 'Branches', 'value' => Branch::query()->where('is_active', true)->count(), 'icon' => 'Building2', 'accent' => 'yellow', 'href' => '/admin/branches'];
            $stats[] = ['key' => 'departments', 'label' => 'Departments', 'value' => Department::query()->where('is_active', true)->count(), 'icon' => 'Network', 'accent' => 'orange', 'href' => '/admin/departments'];
            $stats[] = ['key' => 'teams', 'label' => 'Teams', 'value' => Team::query()->where('is_active', true)->count(), 'icon' => 'UsersRound', 'accent' => 'red', 'href' => '/admin/teams'];
        }

        return $stats;
    }

    /**
     * Chart-ready datasets for the interactive dashboard.
     *
     * @return array<string, mixed>
     */
    private function charts(User $user): array
    {
        $charts = [];

        if ($user->can('purchase-leads.view')) {
            // Pipeline distribution (donut).
            $byStatus = $this->scopedLeads($user)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $pipeline = [];
            foreach (PurchaseLeadStatus::cases() as $status) {
                $count = (int) ($byStatus[$status->value] ?? 0);
                if ($count > 0) {
                    $pipeline[] = ['label' => $status->label(), 'value' => $count];
                }
            }
            $charts['pipeline'] = $pipeline;

            // 14-day new-lead trend (line/area).
            $counts = $this->scopedLeads($user)
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
                ->groupBy('d')
                ->pluck('total', 'd');

            $trend = [];
            for ($i = 13; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $trend[] = [
                    'label' => $day->format('d M'),
                    'value' => (int) ($counts[$day->toDateString()] ?? 0),
                ];
            }
            $charts['leadTrend'] = $trend;
        }

        if ($user->can('vehicles.view')) {
            $stock = $this->scopes
                ->apply(Vehicle::query(), $user, ['branch' => 'branch_id', 'owner' => 'created_by'])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $byStatus = [];
            foreach (VehicleStatus::cases() as $status) {
                $count = (int) ($stock[$status->value] ?? 0);
                if ($count > 0) {
                    $byStatus[] = ['label' => $status->label(), 'value' => $count];
                }
            }
            $charts['stockByStatus'] = $byStatus;
        }

        if ($user->can('payments.view') || $user->can('bookings.view')) {
            // 14-day collections trend (net received per day).
            $rows = BookingPayment::query()
                ->where('status', 'received')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->whereHas('booking', fn ($b) => $this->scopes->apply($b, $user, [
                    'branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by',
                ]))
                ->selectRaw('DATE(created_at) as d, SUM(amount) as total')
                ->groupBy('d')
                ->pluck('total', 'd');

            $revenue = [];
            for ($i = 13; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $revenue[] = [
                    'label' => $day->format('d M'),
                    'value' => round((float) ($rows[$day->toDateString()] ?? 0), 2),
                ];
            }
            $charts['revenueTrend'] = $revenue;
        }

        return $charts;
    }

    private function scopedLeads(User $user)
    {
        return $this->scopes->apply(PurchaseLead::query(), $user, [
            'branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by',
        ]);
    }
}
