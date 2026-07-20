<?php

namespace App\Domain\PublicWebsite\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Enums\EnquiryType;
use App\Domain\PublicWebsite\Models\PublicEnquiry;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Enums\SalesLeadSource;
use Illuminate\Http\Request;

/**
 * Creates a public enquiry with spam/duplicate guards, consent capture, UTM
 * tracking and branch assignment. Vehicle/finance/callback/test-drive enquiries
 * additionally open a Sales Lead in the CRM pipeline; sell-car enquiries flow
 * through SellYourCarAction into a Purchase Lead.
 */
class CreateEnquiryAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly CreateSalesLeadAction $salesLeads,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(EnquiryType $type, array $data, Request $request): PublicEnquiry
    {
        $branchId = $data['branch_id'] ?? null;

        // Inherit the branch from the enquired vehicle when not given.
        if ($branchId === null && ! empty($data['vehicle_id'])) {
            $branchId = Vehicle::query()->whereKey($data['vehicle_id'])->value('branch_id');
        }

        // Duplicate suppression: same mobile + type + vehicle inside the window
        // returns the existing enquiry instead of creating a new row.
        $windowHours = (int) config('car4sales.public.duplicate_window_hours', 12);
        $existing = PublicEnquiry::query()
            ->where('mobile', $data['mobile'])
            ->where('type', $type->value)
            ->when(! empty($data['vehicle_id']), fn ($q) => $q->where('vehicle_id', $data['vehicle_id']))
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $enquiry = PublicEnquiry::query()->create([
            'enquiry_number' => $this->sequences->next('enquiry'),
            'type' => $type->value,
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'message' => $data['message'] ?? null,
            'consent' => (bool) ($data['consent'] ?? false),
            'otp_verified_at' => ($data['otp_verified'] ?? false) ? now() : null,
            'source' => $data['source'] ?? 'website',
            'campaign' => $data['utm']['utm_campaign'] ?? null,
            'utm' => $data['utm'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => (string) str($request->userAgent() ?? '')->limit(500),
            'branch_id' => $branchId,
            'status' => 'new',
        ]);

        // Lead-generating enquiry types open a sales lead in the CRM pipeline.
        if ($type->createsSalesLead()) {
            $lead = $this->salesLeads->execute([
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'interested_vehicle_id' => $data['vehicle_id'] ?? null,
                'finance_required' => $type === EnquiryType::Finance,
                'source' => SalesLeadSource::Website->value,
                'campaign' => $data['utm']['utm_campaign'] ?? null,
                'utm' => $data['utm'] ?? null,
                'branch_id' => $branchId,
                'remarks' => $data['message'] ?? null,
                'priority' => $type === EnquiryType::TestDrive ? 'high' : 'normal',
            ]);

            $enquiry->update(['sales_lead_id' => $lead->id, 'status' => 'converted']);
        }

        return $enquiry;
    }
}
