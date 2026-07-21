<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Models\OtpVerification;
use App\Domain\PublicWebsite\Models\PublicEnquiry;
use App\Domain\PublicWebsite\Models\SellCarRequest;
use App\Domain\PurchaseLeads\Models\PurchaseLead;

it('creates a vehicle enquiry from the public form', function () {
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-E1', 'make' => 'Kia', 'model' => 'Seltos',
        'status' => VehicleStatus::Published->value, 'published_web' => true,
        'asking_price' => 900000, 'branch_id' => Branch::factory()->create()->id,
    ]);

    $this->post('/enquiries', [
        'type' => 'vehicle',
        'name' => 'Test Buyer',
        'mobile' => '9876500011',
        'email' => 'buyer@example.com',
        'vehicle_id' => $vehicle->id,
        'message' => 'Is this available?',
        'consent' => true,
    ])->assertRedirect();

    $enquiry = PublicEnquiry::query()->first();
    expect($enquiry)->not->toBeNull()
        ->and($enquiry->type->value)->toBe('vehicle')
        ->and($enquiry->vehicle_id)->toBe($vehicle->id)
        ->and($enquiry->branch_id)->toBe($vehicle->branch_id) // inherits branch
        ->and($enquiry->enquiry_number)->toStartWith('ENQ-');
});

it('rejects an enquiry without consent', function () {
    $this->post('/enquiries', [
        'type' => 'callback', 'name' => 'No Consent', 'mobile' => '9876500022',
    ])->assertSessionHasErrors('consent');

    expect(PublicEnquiry::query()->count())->toBe(0);
});

it('blocks spam via the honeypot field', function () {
    $this->post('/enquiries', [
        'type' => 'callback', 'name' => 'Bot', 'mobile' => '9876500033',
        'consent' => true, 'company' => 'spam-bot-filled-this',
    ])->assertSessionHasErrors('company');

    expect(PublicEnquiry::query()->count())->toBe(0);
});

it('suppresses duplicate enquiries within the window', function () {
    $payload = ['type' => 'callback', 'name' => 'Dup', 'mobile' => '9876500044', 'consent' => true];

    $this->post('/enquiries', $payload)->assertRedirect();
    $this->post('/enquiries', $payload)->assertRedirect();

    expect(PublicEnquiry::query()->where('mobile', '9876500044')->count())->toBe(1);
});

it('creates a purchase lead from a sell-your-car submission', function () {
    $this->post('/sell-your-car', [
        'seller_name' => 'Seller Person',
        'mobile' => '9876500055',
        'city' => 'Lucknow',
        'make' => 'Hyundai',
        'model' => 'i20',
        'manufacturing_year' => 2019,
        'odometer_km' => 42000,
        'expected_price' => 550000,
        'loan_status' => 'none',
        'consent' => true,
    ])->assertRedirect();

    $lead = PurchaseLead::query()->first();
    $request = SellCarRequest::query()->first();
    $enquiry = PublicEnquiry::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe('website')
        ->and($lead->make)->toBe('Hyundai')
        ->and($request->purchase_lead_id)->toBe($lead->id)
        ->and($enquiry->type->value)->toBe('sell_car')
        ->and($enquiry->purchase_lead_id)->toBe($lead->id)
        ->and($enquiry->status)->toBe('converted');
});

it('runs the OTP request and verify flow', function () {
    $this->postJson('/enquiries/otp/request', ['mobile' => '9876500066'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $otp = OtpVerification::query()->where('mobile', '9876500066')->first();
    expect($otp)->not->toBeNull();

    // Wrong code fails.
    $this->postJson('/enquiries/otp/verify', ['mobile' => '9876500066', 'code' => '000000'])
        ->assertStatus(422);
});
