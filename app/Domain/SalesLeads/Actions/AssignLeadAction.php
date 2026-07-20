<?php

namespace App\Domain\SalesLeads\Actions;

use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadActivity;
use App\Domain\SalesLeads\Models\LeadAssignment;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Assigns or reassigns a lead's telecaller / sales executive, recording the
 * change in assignment history and the activity timeline. A New lead becomes
 * Assigned when it first gets a telecaller.
 */
class AssignLeadAction
{
    public function __construct(private readonly WorkflowService $workflow) {}

    /**
     * @param  'telecaller'|'sales_executive'  $role
     */
    public function execute(SalesLead $lead, string $role, ?int $toUserId, User $actor, ?string $reason = null): SalesLead
    {
        return DB::transaction(function () use ($lead, $role, $toUserId, $actor, $reason) {
            $column = $role === 'sales_executive' ? 'sales_executive_id' : 'telecaller_id';
            $from = $lead->{$column};

            if ($from === $toUserId) {
                return $lead;
            }

            $lead->update([$column => $toUserId]);

            LeadAssignment::query()->create([
                'sales_lead_id' => $lead->id,
                'role' => $role,
                'from_user_id' => $from,
                'to_user_id' => $toUserId,
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);

            $toName = $toUserId ? User::query()->whereKey($toUserId)->value('name') : 'unassigned';
            LeadActivity::query()->create([
                'sales_lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => 'assigned',
                'summary' => str($role)->replace('_', ' ')->title().' assigned to '.$toName,
            ]);

            // First telecaller assignment moves New -> Assigned.
            if ($role === 'telecaller' && $toUserId !== null && $lead->status === SalesLeadStatus::New) {
                $this->workflow->transition($lead, SalesLeadStatus::Assigned, $actor, 'Assigned to telecaller');
            }

            // Notify the new owner (unless they assigned the lead to themselves).
            if ($toUserId !== null && $toUserId !== $actor->id) {
                $assignee = User::query()->find($toUserId);
                if ($assignee !== null) {
                    app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->leadAssigned($lead->fresh(), $assignee);
                }
            }

            return $lead->fresh();
        });
    }
}
