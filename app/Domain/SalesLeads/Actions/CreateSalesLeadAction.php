<?php

namespace App\Domain\SalesLeads\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Customers\Actions\ResolveCustomerAction;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadActivity;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSalesLeadAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly ResolveCustomerAction $customers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $creator = null): SalesLead
    {
        return DB::transaction(function () use ($data, $creator) {
            // Every sales lead is tied to a unified customer record (by mobile).
            $customer = $this->customers->execute([
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
            ]);

            $status = $data['status'] ?? (! empty($data['telecaller_id']) ? SalesLeadStatus::Assigned->value : SalesLeadStatus::New->value);

            $lead = new SalesLead([
                ...$data,
                'lead_number' => $this->sequences->next('sales_lead'),
                'customer_id' => $customer->id,
                'status' => $status,
                'created_by' => $creator?->id,
            ]);
            $lead->save();

            $lead->writeStatusHistory(null, $lead->status->value, $creator, 'Lead created');

            LeadActivity::query()->create([
                'sales_lead_id' => $lead->id,
                'user_id' => $creator?->id,
                'type' => 'created',
                'summary' => 'Lead created from '.($data['source'] ?? 'manual'),
            ]);

            return $lead;
        });
    }
}
