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

function submitKyc(VendorSubmission $submission, User $vendor): VendorSubmission
{
    Storage::fake('private');

    $documents = collect(array_keys(VendorSubmission::REQUIRED_KYC_DOCS))
        ->mapWithKeys(fn ($type) => [$type => UploadedFile::fake()->image("{$type}.jpg")])
        ->all();

    return app(VendorSettlementAction::class)->submitOwnerKyc($submission->fresh(), [
        'owner_name' => 'Rakesh Kumar', 'owner_phone' => '9876540000', 'owner_address' => '14 Civil Lines, Lucknow',
    ], [
        'bank_account_name' => 'VS Motors', 'bank_account_number' => '1234567890',
        'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC Bank',
    ], $documents, $vendor);
}

/** @return array{0: VendorSubmission, 1: User, 2: User} [submission (agreement_ready), vendor, admin] */
function verifiedSubmission(): array
{
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor);
    $submission = app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), $admin);

    return [$submission, $vendor, $admin];
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
        ->and($result->bank_account_number)->toBe('1234567890')
        ->and($result->documentMedia()->count())->toBe(count(VendorSubmission::REQUIRED_KYC_DOCS));
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

it('lets staff verify the documents → agreement ready', function () {
    [$submission, $vendor, $admin] = approvedSubmission();
    submitKyc($submission, $vendor);

    $result = app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), $admin);

    expect($result->settlement_status)->toBe(SettlementStatus::AgreementReady)
        ->and($result->kyc_approved_by)->toBe($admin->id);
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

    submitKyc($submission, $vendor);
    app(VendorSettlementAction::class)->approveOwnerKyc($submission->fresh(), superAdmin());

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
