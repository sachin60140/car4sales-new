<?php

namespace App\Domain\PublicWebsite\Actions;

use App\Domain\PublicWebsite\Enums\EnquiryType;
use App\Domain\PublicWebsite\Models\PublicEnquiry;
use App\Domain\PublicWebsite\Models\SellCarRequest;
use App\Domain\PurchaseLeads\Actions\CreatePurchaseLeadAction;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A public "Sell Your Car" submission: records the request, creates a public
 * enquiry, and automatically opens a Purchase Lead in the acquisition pipeline.
 *
 * @return array{enquiry: PublicEnquiry, request: SellCarRequest, lead: PurchaseLead}
 */
class SellYourCarAction
{
    public function __construct(
        private readonly CreateEnquiryAction $enquiries,
        private readonly CreatePurchaseLeadAction $leads,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{enquiry: PublicEnquiry, request: SellCarRequest, lead: PurchaseLead}
     */
    public function execute(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $enquiry = $this->enquiries->execute(EnquiryType::SellCar, [
                'name' => $data['seller_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'message' => $data['remarks'] ?? null,
                'consent' => $data['consent'] ?? false,
                'otp_verified' => $data['otp_verified'] ?? false,
                'source' => 'website_sell_car',
                'utm' => $data['utm'] ?? null,
            ], $request);

            $lead = $this->leads->execute([
                'seller_name' => $data['seller_name'],
                'seller_type' => 'individual',
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'source' => 'website',
                'registration_number' => $data['registration_number'] ?? null,
                'make' => $data['make'] ?? null,
                'model' => $data['model'] ?? null,
                'variant' => $data['variant'] ?? null,
                'manufacturing_year' => $data['manufacturing_year'] ?? null,
                'odometer_km' => $data['odometer_km'] ?? null,
                'expected_price' => $data['expected_price'] ?? null,
                'loan_status' => $data['loan_status'] ?? 'none',
                'inspection_location' => $data['preferred_inspection_location'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'priority' => 'normal',
            ]);

            $sellRequest = SellCarRequest::query()->create([
                'public_enquiry_id' => $enquiry->id,
                'seller_name' => $data['seller_name'],
                'mobile' => $data['mobile'],
                'city' => $data['city'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'make' => $data['make'] ?? null,
                'model' => $data['model'] ?? null,
                'variant' => $data['variant'] ?? null,
                'manufacturing_year' => $data['manufacturing_year'] ?? null,
                'odometer_km' => $data['odometer_km'] ?? null,
                'expected_price' => $data['expected_price'] ?? null,
                'loan_status' => $data['loan_status'] ?? 'none',
                'preferred_inspection_location' => $data['preferred_inspection_location'] ?? null,
                'preferred_date' => $data['preferred_date'] ?? null,
                'photos' => $data['photos'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'purchase_lead_id' => $lead->id,
            ]);

            $enquiry->update(['purchase_lead_id' => $lead->id, 'status' => 'converted']);

            return ['enquiry' => $enquiry, 'request' => $sellRequest, 'lead' => $lead];
        });
    }
}
