<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    /**
     * Role-aware dashboard counters. Feature phases append their own widgets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $widgets = [];

        if ($user->can('employees.view') || $user->hasRole('Super Admin')) {
            $widgets['employees'] = $this->scopes
                ->applyToUsers(User::query()->where('is_active', true), $user)
                ->count();
        }

        if ($user->can('branches.view') || $user->hasRole('Super Admin')) {
            $widgets['branches'] = Branch::query()->where('is_active', true)->count();
            $widgets['departments'] = Department::query()->where('is_active', true)->count();
            $widgets['teams'] = Team::query()->where('is_active', true)->count();
        }

        if ($user->can('bookings.view')) {
            $widgets['bookings_30d'] = $this->scopes
                ->apply(\App\Domain\Bookings\Models\Booking::query(), $user, ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by'])
                ->where('created_at', '>=', now()->subDays(30))->count();
        }

        if ($user->can('deliveries.view')) {
            $widgets['deliveries_pending'] = $this->scopes
                ->apply(\App\Domain\Deliveries\Models\Delivery::query(), $user, ['branch' => 'branch_id', 'owner' => 'created_by'])
                ->whereIn('status', ['approval_pending', 'approved'])->count();
        }

        if ($user->can('rto-cases.view')) {
            $widgets['rto_open'] = $this->scopes
                ->apply(\App\Domain\RTO\Models\RtoCase::query(), $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by'])
                ->where('status', '!=', 'closed')->count();
        }

        return ApiResponse::success([
            'widgets' => $widgets,
            'notifications_unread' => \App\Domain\Notifications\Models\Notification::query()
                ->where('user_id', $user->id)->whereNull('read_at')->count(),
        ]);
    }
}
