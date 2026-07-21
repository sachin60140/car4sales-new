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

/** @return array{0: VendorSubmission, 1: User, 2: User} [submission, vendor, admin] */
function approvedSubmission(): array
{
    $admin = superAdmin();
    $vendor = app(VendorRegistrationAction::class)->register([
        'name' => 'V', 'email' => 'v'.fake()->unique()->numerify('#####').'@vs.test',
        'password' => 'Password1', 'phone' => '9800000000', 'company_name' => 'VS Motors',
    ]);
    app(VendorRegistrationAction::class)->setStatus($vendor->vendorProfile, VendorProfileStatus::Active, $admin);

    $action = app(VendorSubmissionAction::class);
    $submission = $action->save(null, ['make' => 'Kia', 'model' => 'Seltos', 'expected_amount' => 600000], $vendor->fresh());
    $submission->media()->create(['type' => 'gallery', 'file_path' => 'demo/g.jpg']);
    $action->submit($submission->fresh(), $vendor->fresh());
    $submission = $action->approve($submission->fresh(), $admin);

    return [$submission, $vendor->fresh(), $admin];
}

function requestVendorPayment(VendorSubmission $submission, User $vendor): VendorSubmission
{
    Storage::fake('private');

    return app(VendorSettlementAction::class)->requestPayment($submission->fresh(), [
        'bank_account_name' => 'VS Motors', 'bank_account_number' => '1234567890',
        'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC Bank',
    ], UploadedFile::fake()->image('cheque.jpg'), $vendor);
}

it('opens the settlement (agreement ready) on approval', function () {
    [$submission] = approvedSubmission();

    expect($submission->settlement_status)->toBe(SettlementStatus::AgreementReady);
});

it('downloads the pre-filled agreement pdf for the vendor', function () {
    [$submission, $vendor] = approvedSubmission();

    $this->actingAs($vendor)
        ->get("/submission-agreement/{$submission->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets the vendor request payment with bank details and a cancelled cheque', function () {
    [$submission, $vendor] = approvedSubmission();

    $result = requestVendorPayment($submission, $vendor);

    expect($result->settlement_status)->toBe(SettlementStatus::PaymentRequested)
        ->and($result->bank_account_number)->toBe('1234567890')
        ->and($result->bank_ifsc)->toBe('HDFC0001234')
        ->and($result->chequeMedia()->count())->toBe(1);
});

it('blocks a payment request before approval', function () {
    $vendor = app(VendorRegistrationAction::class)->register([
        'name' => 'V', 'email' => 'pre@vs.test', 'password' => 'Password1', 'phone' => '9800000001',
    ]);
    app(VendorRegistrationAction::class)->setStatus($vendor->vendorProfile, VendorProfileStatus::Active, superAdmin());
    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'A', 'model' => 'B', 'expected_amount' => 1], $vendor->fresh());

    expect(fn () => requestVendorPayment($submission, $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'approved');
});

it('lets staff record the payment with details and a screenshot', function () {
    [$submission, $vendor, $admin] = approvedSubmission();
    requestVendorPayment($submission, $vendor);
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

it('scopes the payment request to the submission owner', function () {
    [$submission] = approvedSubmission();
    $other = app(VendorRegistrationAction::class)->register([
        'name' => 'Other', 'email' => 'other@vs.test', 'password' => 'Password1', 'phone' => '9800000002',
    ]);
    app(VendorRegistrationAction::class)->setStatus($other->vendorProfile, VendorProfileStatus::Active, superAdmin());

    $this->actingAs($other->fresh())
        ->post("/vendor/submissions/{$submission->id}/request-payment", [
            'bank_account_name' => 'X', 'bank_account_number' => '1', 'bank_ifsc' => 'ABCD0001234',
            'cheque' => UploadedFile::fake()->image('c.jpg'),
        ])
        ->assertForbidden();
});
