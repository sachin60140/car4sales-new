<?php

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\VendorSubmissions\Actions\VendorRegistrationAction;
use App\Domain\VendorSubmissions\Actions\VendorSubmissionAction;
use App\Domain\VendorSubmissions\Enums\SubmissionStatus;
use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function registerVendor(array $overrides = []): User
{
    return app(VendorRegistrationAction::class)->register(array_merge([
        'name' => 'V', 'email' => 'v'.fake()->unique()->numerify('#####').'@vend.test',
        'password' => 'Password1', 'phone' => '9800000000', 'company_name' => 'V Motors',
    ], $overrides));
}

function activate(User $vendor): void
{
    app(VendorRegistrationAction::class)->setStatus($vendor->vendorProfile, VendorProfileStatus::Active, superAdmin());
}

function withPhoto(\App\Domain\VendorSubmissions\Models\VendorSubmission $submission, string $type = 'gallery'): void
{
    $submission->media()->create(['type' => $type, 'file_path' => "demo/{$type}.jpg"]);
}

it('registers a vendor as pending activation with the Vendor Partner role', function () {
    $vendor = registerVendor();

    expect($vendor->hasRole('Vendor Partner'))->toBeTrue()
        ->and($vendor->vendorProfile->status)->toBe(VendorProfileStatus::PendingActivation);
});

it('blocks submitting a vehicle until the vendor is activated', function () {
    $vendor = registerVendor();
    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'Maruti', 'model' => 'Swift', 'expected_amount' => 400000], $vendor);

    expect(fn () => app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'activated');
});

it('requires an expected amount before submitting', function () {
    $vendor = registerVendor();
    activate($vendor);
    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'A', 'model' => 'B', 'expected_amount' => 0], $vendor->fresh());

    expect(fn () => app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'expected amount');
});

it('lets an activated vendor submit and staff approve it into a purchase lead', function () {
    $admin = superAdmin();
    $vendor = registerVendor();
    activate($vendor);

    $action = app(VendorSubmissionAction::class);
    $submission = $action->save(null, [
        'make' => 'Hyundai', 'model' => 'i20', 'manufacturing_year' => 2021, 'expected_amount' => 550000,
        'items' => [['section' => 'Engine', 'label' => 'Engine health', 'result' => 'pass', 'rating' => 4]],
    ], $vendor->fresh());

    expect($submission->submission_number)->toStartWith('VSUB-')
        ->and($submission->items)->toHaveCount(1);

    withPhoto($submission);
    $action->submit($submission->fresh(), $vendor->fresh());
    expect($submission->fresh()->status)->toBe(SubmissionStatus::PendingReview);

    $submission = $action->approve($submission->fresh(), $admin, 'Looks good');

    expect($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->purchase_lead_id)->not->toBeNull();

    $lead = PurchaseLead::find($submission->purchase_lead_id);
    expect($lead->source)->toBe('vendor')
        ->and($lead->make)->toBe('Hyundai')
        ->and((float) $lead->expected_price)->toBe(550000.0);
});

it('rejects a submission with a required reason', function () {
    $admin = superAdmin();
    $vendor = registerVendor();
    activate($vendor);

    $action = app(VendorSubmissionAction::class);
    $submission = $action->save(null, ['make' => 'A', 'model' => 'B', 'expected_amount' => 100000], $vendor->fresh());
    withPhoto($submission);
    $action->submit($submission->fresh(), $vendor->fresh());
    $action->reject($submission->fresh(), $admin, 'Price too high');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Rejected)
        ->and($submission->fresh()->review_remarks)->toBe('Price too high');
});

it('auto-calculates the overall rating from the checklist item ratings, ignoring any supplied value', function () {
    $vendor = registerVendor();
    activate($vendor);

    $submission = app(VendorSubmissionAction::class)->save(null, [
        'make' => 'A', 'model' => 'B', 'expected_amount' => 1,
        'overall_rating' => 1, // supplied but must be ignored
        'items' => [
            ['section' => 'X', 'label' => 'a', 'result' => 'pass', 'rating' => 5],
            ['section' => 'X', 'label' => 'b', 'result' => 'pass', 'rating' => 3],
            ['section' => 'X', 'label' => 'c', 'result' => 'na', 'rating' => null],
        ],
    ], $vendor->fresh());

    // Average of the rated items (5, 3) = 4; the N/A item is excluded.
    expect($submission->overall_rating)->toBe(4);
});

it('requires at least one vehicle photo before submitting', function () {
    $vendor = registerVendor();
    activate($vendor);
    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'Tata', 'model' => 'Nexon', 'expected_amount' => 500000], $vendor->fresh());

    expect(fn () => app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'vehicle photo');

    withPhoto($submission);
    app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh());
    expect($submission->fresh()->status)->toBe(SubmissionStatus::PendingReview);
});

it('requires a damage photo when a checklist item is failed', function () {
    $vendor = registerVendor();
    activate($vendor);
    $submission = app(VendorSubmissionAction::class)->save(null, [
        'make' => 'Tata', 'model' => 'Punch', 'expected_amount' => 500000,
        'items' => [['section' => 'Exterior', 'label' => 'Body & paint', 'result' => 'fail', 'rating' => 2]],
    ], $vendor->fresh());
    withPhoto($submission, 'gallery');

    expect(fn () => app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh()))
        ->toThrow(RuntimeException::class, 'damage');

    withPhoto($submission, 'damage');
    app(VendorSubmissionAction::class)->submit($submission->fresh(), $vendor->fresh());
    expect($submission->fresh()->status)->toBe(SubmissionStatus::PendingReview);
});

it('registers a vendor through the web and lands on the portal', function () {
    $this->post('/vendor/register', [
        'name' => 'Web Vendor', 'phone' => '9811111111', 'email' => 'webv@vend.test',
        'password' => 'Password1', 'password_confirmation' => 'Password1',
    ])->assertRedirect('/vendor');

    expect(User::where('email', 'webv@vend.test')->first()?->hasRole('Vendor Partner'))->toBeTrue();
});

it('scopes a vendor to only their own submissions', function () {
    $vendorA = registerVendor();
    $vendorB = registerVendor();
    activate($vendorA);

    $submission = app(VendorSubmissionAction::class)->save(null, ['make' => 'X', 'model' => 'Y', 'expected_amount' => 1], $vendorA->fresh());

    $this->actingAs($vendorB->fresh())->get("/vendor/submissions/{$submission->id}")->assertForbidden();
    $this->actingAs($vendorA->fresh())->get("/vendor/submissions/{$submission->id}")->assertOk();
});

it('approves a submission through the admin endpoint, creating the lead', function () {
    $admin = superAdmin();
    $vendor = registerVendor();
    activate($vendor);

    $action = app(VendorSubmissionAction::class);
    $submission = $action->save(null, ['make' => 'Kia', 'model' => 'Seltos', 'expected_amount' => 700000], $vendor->fresh());
    withPhoto($submission);
    $action->submit($submission->fresh(), $vendor->fresh());

    $this->actingAs($admin)
        ->post("/admin/vendor-submissions/{$submission->id}/approve", ['remarks' => 'ok'])
        ->assertRedirect();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->fresh()->purchase_lead_id)->not->toBeNull();
});
