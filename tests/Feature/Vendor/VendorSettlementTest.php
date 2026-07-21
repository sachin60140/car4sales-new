<?php

use App\Domain\VendorSubmissions\Actions\VendorRegistrationAction;
use App\Domain\VendorSubmissions\Actions\VendorSettlementAction;
use App\Domain\VendorSubmissions\Actions\VendorSubmissionAction;
use App\Domain\VendorSubmissions\Enums\SettlementStatus;
use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

/** @return array{0: VendorSubmission, 1: User, 2: User} [submission (kyc_pending), vendor, admin] */
function approvedSubmission(): array
{
    $admin = superAdmin();
    $vendor = app(VendorRegistrationAction::class)->register([
        'name' => 'V', 'email' => 'v'.fake()->unique()->numerify('#####').'@vs.test',
        'password' => 'Password1', 'phone' => '9800000000', 'company_name' => 'VS Motors',
    ]);
    app(VendorRegistrationAction::class)->setStatus($vendor->vendorProfile, VendorProfileStatus::Active, $admin);

    $action = app(VendorSubmissionAction::class);
    $submission = $action->save(null, ['make' => 'Kia', 'model' => 'Seltos', 'registration_number' => 'UP32 AA 0001', 'expected_amount' => 600000], $vendor->fresh());
    $submission->media()->create(['type' => 'gallery', 'file_path' => 'demo/g.jpg']);
    $action->submit($submission->fresh(), $vendor->fresh());
    $submission = $action->approve($submission->fresh(), $admin);

    return [$submission, $vendor->fresh(), $admin];
}

function submitKyc(VendorSubmission $submission, User $vendor, bool $hasHypothecation = false): VendorSubmission
{
    Storage::fake('private');

    // A fake file for every media type of every required/conditional catalog doc.
    $files = [];
    foreach (VendorSubmission::documentCatalog($hasHypothecation) as $key => $def) {
        if (! in_array($def['group'], ['required', 'conditional'], true)) {
            continue;
        }
        foreach (VendorSubmission::docMediaTypes($key, $def['sides']) as $type) {
            $files[$type] = UploadedFile::fake()->image("{$type}.jpg");
        }
    }

    return app(VendorSettlementAction::class)->submitOwnerKyc($submission->fresh(), [
        'owner_name' => 'Rakesh Kumar', 'owner_phone' => '9876540000', 'owner_address' => '14 Civil Lines, Lucknow',
        'chassis_number' => 'MAT625016PWA12345', 'has_hypothecation' => $hasHypothecation,
    ], [
        'bank_account_name' => 'Rakesh Kumar', 'bank_account_number' => '1234567890',
        'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC Bank',
    ], $files, $vendor);
}

function verifyAllDocs(VendorSubmission $submission, User $admin): void
{
    $action = app(VendorSettlementAction::class);
    foreach (VendorSubmission::requiredDocKeys($submission->has_hypothecation) as $key) {
        $action->verifyDocument($submission->fresh(), $key, ['status' => 'verified'], $admin);
    }
}

/** @return array{0: VendorSubmission, 1: User, 2: User} [submission (agreement_ready), vendor, admin] */
function verifiedSubmission(): array
{
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor);
    verifyAllDocs($submission->fresh(), $admin);
    $submission = app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), $admin);

    return [$submission, $vendor, $admin];
}

/** @return array{0: VendorSubmission, 1: User, 2: User} [submission (paid), vendor, admin] */
function paidSubmission(): array
{
    [$submission, $vendor, $admin] = verifiedSubmission();
    $action = app(VendorSettlementAction::class);
    $action->requestPayment($submission->fresh(), $vendor);
    $action->recordPayment($submission->fresh(), [
        'payment_amount' => 590000, 'payment_mode' => 'neft',
        'payment_reference' => 'UTR1', 'payment_date' => now()->toDateString(),
    ], null, $admin);

    return [$submission->fresh(), $vendor, $admin];
}

it('opens owner-KYC (kyc pending) on approval', function () {
    [$submission] = approvedSubmission();

    expect($submission->settlement_status)->toBe(SettlementStatus::KycPending);
});

it('lets the vendor submit owner details, bank and documents', function () {
    [$submission, $vendor] = approvedSubmission();

    $result = submitKyc($submission, $vendor);

    expect($result->settlement_status)->toBe(SettlementStatus::KycSubmitted)
        ->and($result->owner_name)->toBe('Rakesh Kumar')
        ->and($result->chassis_number)->toBe('MAT625016PWA12345')
        ->and($result->bank_account_number)->toBe('1234567890')
        // 8 media rows: RC front/back, Aadhaar front/back, PAN, chassis, owner photo, cheque.
        ->and($result->documentMedia()->count())->toBe(8)
        ->and($result->media()->whereIn('type', ['rc_front', 'rc_back', 'aadhaar_front', 'aadhaar_back'])->count())->toBe(4);
});

it('records keys availability on the submission', function () {
    $vendor = app(VendorRegistrationAction::class)->register([
        'name' => 'V', 'email' => 'keys@vs.test', 'password' => 'Password1', 'phone' => '9800000003',
    ]);
    $submission = app(VendorSubmissionAction::class)->save(null, [
        'make' => 'A', 'model' => 'B', 'registration_number' => 'X', 'expected_amount' => 1, 'keys_available' => 'both',
    ], $vendor->fresh());

    expect($submission->keys_available)->toBe('both');
});

it('blocks owner-KYC before approval', function () {
    $vendor = app(VendorRegistrationAction::class)->register([
        'name' => 'V', 'email' => 'pre@vs.test', 'password' => 'Password1', 'phone' => '9800000001',
    ]);
    app(VendorRegistrationAction::class)->setStatus($vendor->vendorProfile, VendorProfileStatus::Active, superAdmin());
    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'A', 'model' => 'B', 'registration_number' => 'X', 'expected_amount' => 1], $vendor->fresh());

    expect(fn () => submitKyc($submission, $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'approved');
});

it('verifies each document and blocks issuing the agreement until all required are verified', function () {
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor);
    $action = app(VendorSettlementAction::class);

    // Verify one document, but not all — issuing must still be blocked.
    $action->verifyDocument($submission->fresh(), 'rc', ['status' => 'verified'], $admin);
    expect(fn () => $action->approveOwnerKyc($submission->fresh(), $admin))
        ->toThrow(RuntimeException::class, 'Verify all required');

    verifyAllDocs($submission->fresh(), $admin);
    $result = $action->approveOwnerKyc($submission->fresh(), $admin);

    expect($result->settlement_status)->toBe(SettlementStatus::AgreementReady)
        ->and($result->kyc_approved_by)->toBe($admin->id);
});

it('requires NOC & Form 35 as required documents only under hypothecation', function () {
    expect(VendorSubmission::requiredDocKeys(false))->not->toContain('noc')->not->toContain('form_35')
        ->and(VendorSubmission::requiredDocKeys(true))->toContain('noc')->toContain('form_35');

    // A hypothecation submission that supplies NOC & Form 35 verifies through to agreement.
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor, hasHypothecation: true);
    verifyAllDocs($submission->fresh(), $admin);
    $result = app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), $admin);

    expect($submission->fresh()->has_hypothecation)->toBeTrue()
        ->and($result->settlement_status)->toBe(SettlementStatus::AgreementReady);
});

it('lets staff send documents back to the vendor', function () {
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor);

    $result = app(VendorSettlementAction::class)->rejectOwnerKyc($submission->fresh(), $admin, 'Aadhaar is blurred.');

    expect($result->settlement_status)->toBe(SettlementStatus::KycPending)
        ->and($result->kyc_remarks)->toBe('Aadhaar is blurred.');
});

it('only serves the agreement once documents are verified', function () {
    [$submission, $vendor] = approvedSubmission();

    // Before verification — not available.
    $this->actingAs($vendor)->get("/submission-agreement/{$submission->id}")->assertNotFound();

    $admin = superAdmin();
    submitKyc($submission, $vendor);
    verifyAllDocs($submission->fresh(), $admin);
    app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), $admin);

    $this->actingAs($vendor)
        ->get("/submission-agreement/{$submission->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets the vendor request payment once verified', function () {
    [$submission, $vendor] = verifiedSubmission();

    $result = app(VendorSettlementAction::class)->requestPayment($submission->fresh(), $vendor);

    expect($result->settlement_status)->toBe(SettlementStatus::PaymentRequested);
});

it('blocks a payment request before documents are verified', function () {
    [$submission, $vendor] = approvedSubmission();

    expect(fn () => app(VendorSettlementAction::class)->requestPayment($submission->fresh(), $vendor))
        ->toThrow(RuntimeException::class, 'verified');
});

it('lets staff record the payment with details and a screenshot', function () {
    [$submission, $vendor, $admin] = verifiedSubmission();
    app(VendorSettlementAction::class)->requestPayment($submission->fresh(), $vendor);
    Storage::fake('private');

    $this->actingAs($admin)
        ->post("/admin/vendor-submissions/{$submission->id}/record-payment", [
            'payment_amount' => 595000, 'payment_mode' => 'neft',
            'payment_reference' => 'UTR12345', 'payment_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertRedirect();

    $submission->refresh();
    expect($submission->settlement_status)->toBe(SettlementStatus::Paid)
        ->and((float) $submission->payment_amount)->toBe(595000.0)
        ->and($submission->payment_mode)->toBe('neft')
        ->and($submission->paymentProofMedia()->count())->toBe(1);
});

it('confirms possession after payment and creates stock', function () {
    [$submission, , $admin] = paidSubmission();

    $result = app(VendorSettlementAction::class)->confirmPossession($submission->fresh(), [
        'vehicle_received' => true, 'original_rc_received' => true, 'main_key' => true,
        'odometer_km' => 33000, 'fuel_level' => 'Half',
    ], $admin);

    $submission->refresh();
    $vehicle = $result['vehicle'];

    expect($submission->settlement_status)->toBe(SettlementStatus::Stocked)
        ->and($submission->vehicle_id)->toBe($vehicle->id)
        ->and($vehicle->registration_number)->toBe('UP32 AA 0001')
        ->and((float) $vehicle->purchase_price)->toBe(590000.0)
        ->and($vehicle->odometer_km)->toBe(33000)
        ->and(\App\Domain\Inventory\Models\Vehicle::whereKey($vehicle->id)->where('status', 'in_stock')->exists())->toBeTrue();
});

it('blocks possession before the payment is recorded', function () {
    [$submission, , $admin] = verifiedSubmission();

    expect(fn () => app(VendorSettlementAction::class)->confirmPossession($submission->fresh(), ['vehicle_received' => true], $admin))
        ->toThrow(RuntimeException::class, 'payment');
});

it('requires the vehicle to be received to confirm possession', function () {
    [$submission, , $admin] = paidSubmission();

    expect(fn () => app(VendorSettlementAction::class)->confirmPossession($submission->fresh(), ['vehicle_received' => false], $admin))
        ->toThrow(RuntimeException::class, 'received');
});

it('confirms possession + creates stock through the admin endpoint', function () {
    [$submission, , $admin] = paidSubmission();

    $this->actingAs($admin)
        ->post("/admin/vendor-submissions/{$submission->id}/confirm-possession", [
            'vehicle_received' => true, 'main_key' => true, 'odometer_km' => 33000,
        ])
        ->assertRedirect();

    $submission->refresh();
    expect($submission->settlement_status)->toBe(SettlementStatus::Stocked)
        ->and($submission->vehicle_id)->not->toBeNull();
});

it('scopes owner-KYC submission to the submission owner', function () {
    [$submission] = approvedSubmission();
    $other = app(VendorRegistrationAction::class)->register([
        'name' => 'Other', 'email' => 'other@vs.test', 'password' => 'Password1', 'phone' => '9800000002',
    ]);
    app(VendorRegistrationAction::class)->setStatus($other->vendorProfile, VendorProfileStatus::Active, superAdmin());

    $this->actingAs($other->fresh())
        ->post("/vendor/submissions/{$submission->id}/owner-kyc", [
            'owner_name' => 'X', 'owner_phone' => '1', 'owner_address' => 'Y',
            'bank_account_name' => 'X', 'bank_account_number' => '1', 'bank_ifsc' => 'ABCD0001234',
        ])
        ->assertForbidden();
});
