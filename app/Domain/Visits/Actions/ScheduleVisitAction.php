<?php

namespace App\Domain\Visits\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadActivity;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\Visits\Enums\VisitStatus;
use App\Domain\Visits\Models\CustomerVisit;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;

class ScheduleVisitAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly WorkflowService $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(SalesLead $lead, array $data, User $actor): CustomerVisit
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $visit = CustomerVisit::query()->create([
                'visit_number' => $this->sequences->next('visit'),
                'sales_lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'branch_id' => $data['branch_id'] ?? $lead->branch_id,
                'scheduled_at' => $data['scheduled_at'],
                'attended_by' => $data['attended_by'] ?? $lead->sales_executive_id,
                'interested_vehicle_ids' => $data['interested_vehicle_ids'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => VisitStatus::Scheduled->value,
                'created_by' => $actor->id,
            ]);

            LeadActivity::query()->create([
                'sales_lead_id' => $lead->id, 'user_id' => $actor->id,
                'type' => 'visit', 'summary' => 'Visit scheduled for '.$visit->scheduled_at?->format('d M Y H:i'),
            ]);

            if (in_array($lead->status, [SalesLeadStatus::Contacted, SalesLeadStatus::Interested, SalesLeadStatus::FollowUp], true)) {
                $this->workflow->transition($lead, SalesLeadStatus::VisitScheduled, $actor, 'Visit scheduled', force: true);
            }

            return $visit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(CustomerVisit $visit, array $data, User $actor): CustomerVisit
    {
        return DB::transaction(function () use ($visit, $data, $actor) {
            $visit->update([
                'status' => VisitStatus::Completed->value,
                'arrived_at' => $data['arrived_at'] ?? now(),
                'confirmed' => true,
                'outcome' => $data['outcome'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'remarks' => $data['remarks'] ?? $visit->remarks,
            ]);

            if ($visit->lead && $visit->lead->status === SalesLeadStatus::VisitScheduled) {
                $this->workflow->transition($visit->lead, SalesLeadStatus::VisitCompleted, $actor, 'Visit completed', force: true);
            }

            LeadActivity::query()->create([
                'sales_lead_id' => $visit->sales_lead_id, 'user_id' => $actor->id,
                'type' => 'visit', 'summary' => 'Visit completed'.($data['outcome'] ?? null ? ' — '.$data['outcome'] : ''),
            ]);

            return $visit;
        });
    }
}
