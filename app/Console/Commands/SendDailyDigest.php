<?php

namespace App\Console\Commands;

use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Composes a per-manager daily digest (scoped to what each recipient may see)
 * and sends it as an in-app + mail notification. Scheduled nightly; safe to run
 * manually since XAMPP has no cron:  php artisan reports:daily-digest
 */
class SendDailyDigest extends Command
{
    protected $signature = 'reports:daily-digest {--date= : Digest date (Y-m-d), defaults to today}';

    protected $description = 'Send the daily activity digest to managers.';

    public function handle(NotificationService $notifications, ScopeService $scopes): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        [$from, $to] = [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

        $roleNames = Role::query()
            ->whereIn('name', ['Director', 'Owner', 'Branch Manager', 'Sales Manager', 'Super Admin'])
            ->pluck('name')->all();

        $recipients = $roleNames === []
            ? collect()
            : User::query()->where('is_active', true)->role($roleNames)->get();

        if ($recipients->isEmpty()) {
            $this->info('No digest recipients found.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($recipients as $user) {
            $newLeads = $scopes->apply(SalesLead::query(), $user, ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by'])
                ->whereBetween('created_at', [$from, $to])->count();

            $bookings = $scopes->apply(Booking::query(), $user, ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by'])
                ->whereBetween('created_at', [$from, $to])->count();

            $delivered = $scopes->apply(Delivery::query(), $user, ['branch' => 'branch_id', 'owner' => 'created_by'])
                ->whereBetween('delivered_at', [$from, $to])->count();

            $rtoOpen = $scopes->apply(RtoCase::query(), $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by'])
                ->where('status', '!=', RtoStatus::Closed->value)->count();

            $pendingApprovals = ApprovalRequest::query()
                ->where('status', ApprovalStatus::Pending->value)
                ->whereIn('current_role_id', $user->roles->pluck('id'))
                ->count();

            $body = sprintf(
                'New leads: %d · Bookings: %d · Delivered: %d · RTO open: %d · Approvals awaiting you: %d',
                $newLeads, $bookings, $delivered, $rtoOpen, $pendingApprovals,
            );

            $notifications->notify($user, 'digest.daily', 'Daily digest — '.$date->format('d M Y'), [
                'level' => $pendingApprovals > 0 ? NotificationLevel::Warning : NotificationLevel::Info,
                'body' => $body,
                'action_url' => '/admin/reports',
                'data' => compact('newLeads', 'bookings', 'delivered', 'rtoOpen', 'pendingApprovals'),
            ]);
            $sent++;
        }

        $this->info("Daily digest sent to {$sent} manager(s).");

        return self::SUCCESS;
    }
}
