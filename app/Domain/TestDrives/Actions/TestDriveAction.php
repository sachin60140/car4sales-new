<?php

namespace App\Domain\TestDrives\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadActivity;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\TestDrives\Models\TestDrive;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;

class TestDriveAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly WorkflowService $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(SalesLead $lead, Vehicle $vehicle, array $data, User $actor): TestDrive
    {
        return DB::transaction(function () use ($lead, $vehicle, $data, $actor) {
            $td = TestDrive::query()->create([
                'td_number' => $this->sequences->next('test_drive'),
                'sales_lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'vehicle_id' => $vehicle->id,
                'branch_id' => $data['branch_id'] ?? $lead->branch_id,
                'driving_licence_number' => $data['driving_licence_number'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'accompanied_by' => $data['accompanied_by'] ?? $lead->sales_executive_id,
                'status' => 'scheduled',
                'created_by' => $actor->id,
            ]);

            LeadActivity::query()->create([
                'sales_lead_id' => $lead->id, 'user_id' => $actor->id,
                'type' => 'test_drive', 'summary' => 'Test drive scheduled — '.trim($vehicle->make.' '.$vehicle->model),
            ]);

            if (in_array($lead->status, [SalesLeadStatus::Interested, SalesLeadStatus::FollowUp, SalesLeadStatus::VisitCompleted, SalesLeadStatus::VisitScheduled], true)) {
                $this->workflow->transition($lead, SalesLeadStatus::TestDrive, $actor, 'Test drive scheduled', force: true);
            }

            return $td;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(TestDrive $td, array $data, User $actor): TestDrive
    {
        $td->update([
            'status' => 'completed',
            'start_at' => $data['start_at'] ?? $td->start_at ?? now(),
            'end_at' => $data['end_at'] ?? now(),
            'start_odometer' => $data['start_odometer'] ?? $td->start_odometer,
            'end_odometer' => $data['end_odometer'] ?? null,
            'fuel_level' => $data['fuel_level'] ?? null,
            'route' => $data['route'] ?? null,
            'damage_acknowledged' => $data['damage_acknowledged'] ?? false,
            'feedback' => $data['feedback'] ?? null,
            'customer_signature_path' => $data['customer_signature_path'] ?? $td->customer_signature_path,
        ]);

        LeadActivity::query()->create([
            'sales_lead_id' => $td->sales_lead_id, 'user_id' => $actor->id,
            'type' => 'test_drive', 'summary' => 'Test drive completed'.($data['feedback'] ?? null ? ' — '.$data['feedback'] : ''),
        ]);

        return $td;
    }
}
