<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Translates domain events into notifications with the right recipients. This is
 * the single seam workflow hooks call, so recipient logic lives in one place and
 * the models/engine stay free of notification concerns.
 */
class NotificationDispatcher
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Notify the approvers of the current step that an approval is waiting. */
    public function approvalRequested(ApprovalRequest $request): void
    {
        if ($request->current_role_id === null) {
            return;
        }

        $roleName = Role::query()->whereKey($request->current_role_id)->value('name');
        if ($roleName === null) {
            return;
        }

        $recipients = $this->notifications->usersWithRole($roleName, $request->branch_id);

        $this->notifications->notifyMany($recipients, 'approval.requested', 'Approval required', [
            'level' => NotificationLevel::Warning,
            'body' => $request->reason ?? ('Approval '.$request->approval_number.' awaits your decision.'),
            'branch_id' => $request->branch_id,
            'data' => ['approval_number' => $request->approval_number, 'module' => $request->module],
        ]);
    }

    /** Notify the requester of the final decision. */
    public function approvalDecided(ApprovalRequest $request, string $decision): void
    {
        $requester = $request->requester;
        if ($requester === null) {
            return;
        }

        $approved = $decision === 'approved';

        $this->notifications->notify($requester, 'approval.decided', 'Approval '.$decision, [
            'level' => $approved ? NotificationLevel::Success : NotificationLevel::Critical,
            'body' => 'Your request '.$request->approval_number.' was '.$decision.'.',
            'branch_id' => $request->branch_id,
            'data' => ['approval_number' => $request->approval_number, 'decision' => $decision],
        ]);
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $recipients = $this->managers($booking->branch_id, ['Sales Manager', 'Branch Manager'])
            ->push($booking->salesExecutive)
            ->filter();

        $this->notifications->notifyMany($recipients, 'booking.confirmed', 'Booking confirmed', [
            'level' => NotificationLevel::Success,
            'body' => 'Booking '.$booking->booking_number.' has been confirmed.',
            'action_url' => '/admin/bookings/'.$booking->id,
            'branch_id' => $booking->branch_id,
            'data' => ['booking_number' => $booking->booking_number],
        ]);
    }

    public function financeStatusChanged(FinanceApplication $application, FinanceStatus $to): void
    {
        [$title, $level] = match ($to) {
            FinanceStatus::Sanctioned => ['Finance sanctioned', NotificationLevel::Success],
            FinanceStatus::Disbursed => ['Finance disbursed', NotificationLevel::Success],
            FinanceStatus::Rejected => ['Finance rejected', NotificationLevel::Critical],
            default => [null, NotificationLevel::Info],
        };

        if ($title === null) {
            return;
        }

        $recipients = collect([$this->user($application->assigned_to), $this->user($application->created_by)]);
        if ($to === FinanceStatus::Disbursed) {
            $recipients = $recipients->merge($this->managers($application->branch_id, ['Accounts Manager', 'Finance Manager']));
        }

        $this->notifications->notifyMany($recipients->filter(), 'finance.'.$to->value, $title, [
            'level' => $level,
            'body' => 'Finance file '.$application->application_number.' is now '.$to->label().'.',
            'action_url' => '/admin/finance/'.$application->id,
            'branch_id' => $application->branch_id,
            'data' => ['application_number' => $application->application_number, 'status' => $to->value],
        ]);
    }

    public function rtoStatusChanged(RtoCase $case, RtoStatus $to): void
    {
        $recipients = collect([$this->user($case->assigned_to)]);
        $level = NotificationLevel::Info;

        if ($to === RtoStatus::Closed) {
            $recipients = $recipients->merge($this->managers($case->branch_id, ['RTO Manager', 'Branch Manager']));
            $level = NotificationLevel::Success;
        }

        if ($recipients->filter()->isEmpty()) {
            return;
        }

        $this->notifications->notifyMany($recipients->filter(), 'rto.'.$to->value, 'RTO '.$to->label(), [
            'level' => $level,
            'body' => 'Transfer case '.$case->rto_number.' moved to '.$to->label().'.',
            'action_url' => '/admin/rto-cases/'.$case->id,
            'branch_id' => $case->branch_id,
            'data' => ['rto_number' => $case->rto_number, 'status' => $to->value],
        ]);
    }

    public function deliveryApproved(Delivery $delivery): void
    {
        $recipient = $this->user($delivery->created_by);
        if ($recipient === null) {
            return;
        }

        $this->notifications->notify($recipient, 'delivery.approved', 'Delivery approved', [
            'level' => NotificationLevel::Success,
            'body' => 'Delivery '.$delivery->delivery_number.' is approved and ready for handover.',
            'action_url' => '/admin/deliveries/'.$delivery->id,
            'branch_id' => $delivery->branch_id,
            'data' => ['delivery_number' => $delivery->delivery_number],
        ]);
    }

    public function deliveryCompleted(Delivery $delivery): void
    {
        $recipients = $this->managers($delivery->branch_id, ['Sales Manager', 'Branch Manager', 'RTO Manager'])
            ->push($delivery->booking?->salesExecutive)
            ->filter();

        $this->notifications->notifyMany($recipients, 'delivery.completed', 'Vehicle delivered', [
            'level' => NotificationLevel::Success,
            'body' => 'Delivery '.$delivery->delivery_number.' is complete. RTO transfer has begun.',
            'action_url' => '/admin/deliveries/'.$delivery->id,
            'branch_id' => $delivery->branch_id,
            'data' => ['delivery_number' => $delivery->delivery_number],
        ]);
    }

    public function leadAssigned(SalesLead $lead, User $assignee): void
    {
        $this->notifications->notify($assignee, 'lead.assigned', 'New lead assigned', [
            'level' => NotificationLevel::Info,
            'body' => 'Lead '.$lead->lead_number.' has been assigned to you.',
            'action_url' => '/admin/sales-leads/'.$lead->id,
            'branch_id' => $lead->branch_id,
            'data' => ['lead_number' => $lead->lead_number],
        ]);
    }

    /** @return Collection<int, User> */
    private function managers(?int $branchId, array $roles): Collection
    {
        return $this->notifications->usersWithRole($roles, $branchId);
    }

    private function user(?int $id): ?User
    {
        return $id === null ? null : User::query()->find($id);
    }
}
