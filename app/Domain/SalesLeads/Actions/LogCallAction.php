<?php

namespace App\Domain\SalesLeads\Actions;

use App\Domain\SalesLeads\Enums\CallOutcome;
use App\Domain\SalesLeads\Models\LeadActivity;
use App\Domain\SalesLeads\Models\LeadFollowup;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a manual call outcome and applies the business rules (spec §18):
 *  - every "interested" lead must have a next follow-up;
 *  - every lost outcome (not-interested / wrong-number) must carry a lost reason;
 *  - the lead status advances/regresses to match the outcome;
 *  - the first connected call stamps first_response_at (for response-time reports).
 */
class LogCallAction
{
    public function __construct(private readonly WorkflowService $workflow) {}

    /**
     * @param  array<string, mixed>  $data  {channel, remarks?, next_follow_up_at?, duration_seconds?, lost_reason_id?}
     */
    public function execute(SalesLead $lead, CallOutcome $outcome, array $data, User $actor): LeadFollowup
    {
        $nextFollowUp = $data['next_follow_up_at'] ?? null;
        $lostReasonId = $data['lost_reason_id'] ?? null;

        if ($outcome->requiresFollowUp() && empty($nextFollowUp)) {
            throw ValidationException::withMessages([
                'next_follow_up_at' => 'A next follow-up date is required for this outcome.',
            ]);
        }

        if ($outcome->terminalStatus()?->isLost() && empty($lostReasonId)) {
            throw ValidationException::withMessages([
                'lost_reason_id' => 'A reason is required when marking the lead as lost.',
            ]);
        }

        return DB::transaction(function () use ($lead, $outcome, $data, $actor, $nextFollowUp, $lostReasonId) {
            $followup = LeadFollowup::query()->create([
                'sales_lead_id' => $lead->id,
                'user_id' => $actor->id,
                'channel' => $data['channel'] ?? 'call',
                'call_outcome' => $outcome->value,
                'remarks' => $data['remarks'] ?? null,
                'next_follow_up_at' => $nextFollowUp,
                'duration_seconds' => $data['duration_seconds'] ?? null,
            ]);

            $updates = ['next_follow_up_at' => $nextFollowUp ?: $lead->next_follow_up_at];
            if ($lead->first_response_at === null && $outcome->isConnected()) {
                $updates['first_response_at'] = now();
            }
            if ($lostReasonId !== null) {
                $updates['lost_reason_id'] = $lostReasonId;
            }
            $lead->update($updates);

            LeadActivity::query()->create([
                'sales_lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => 'call',
                'summary' => $outcome->label().(($data['remarks'] ?? null) ? ' — '.$data['remarks'] : ''),
                'properties' => ['outcome' => $outcome->value, 'channel' => $data['channel'] ?? 'call'],
            ]);

            // Advance / terminate the lead status to match the outcome. The manual
            // call outcome is authoritative, so the move is forced — but a lead in a
            // terminal state (Delivered) is never dragged backwards.
            $target = $outcome->terminalStatus() ?? $outcome->advanceStatus();
            if ($target !== null && $target !== $lead->status && ! $lead->status->isTerminal()) {
                $this->workflow->transition($lead, $target, $actor, 'Call outcome: '.$outcome->label(), force: true);
            }

            return $followup;
        });
    }
}
