<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Models\PublicEnquiry;
use App\Domain\SalesLeads\Models\SalesLead;

it('converts a vehicle enquiry into a sales lead', function () {
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-C1', 'make' => 'Kia', 'model' => 'Seltos',
        'status' => VehicleStatus::Published->value, 'published_web' => true,
        'asking_price' => 900000, 'branch_id' => Branch::factory()->create()->id,
    ]);

    $this->post('/enquiries', [
        'type' => 'vehicle', 'name' => 'Web Visitor', 'mobile' => '9876543210',
        'vehicle_id' => $vehicle->id, 'consent' => true,
    ])->assertRedirect();

    $enquiry = PublicEnquiry::query()->first();
    $lead = SalesLead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe('website')
        ->and($lead->interested_vehicle_id)->toBe($vehicle->id)
        ->and($lead->branch_id)->toBe($vehicle->branch_id)
        ->and($enquiry->sales_lead_id)->toBe($lead->id)
        ->and($enquiry->status)->toBe('converted');
});

it('flags finance enquiries as finance-required leads', function () {
    $this->post('/enquiries', [
        'type' => 'finance', 'name' => 'Loan Seeker', 'mobile' => '9876500000', 'consent' => true,
    ])->assertRedirect();

    expect(SalesLead::query()->first()->finance_required)->toBeTrue();
});

it('does not create a sales lead for a plain contact message', function () {
    $this->post('/enquiries', [
        'type' => 'contact', 'name' => 'Just Asking', 'mobile' => '9876511111',
        'message' => 'What are your timings?', 'consent' => true,
    ])->assertRedirect();

    expect(SalesLead::query()->count())->toBe(0)
        ->and(PublicEnquiry::query()->first()->sales_lead_id)->toBeNull();
});
