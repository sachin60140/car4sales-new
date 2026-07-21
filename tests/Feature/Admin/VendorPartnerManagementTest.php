<?php

use App\Domain\VendorSubmissions\Actions\VendorPartnerKycAction;
use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makePartner(string $email = 'partner@p.test'): VendorProfile
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole('Vendor Partner');

    return $user->vendorProfile()->create([
        'company_name' => 'P Co', 'contact_person' => 'P',
        'status' => VendorProfileStatus::PendingActivation->value, 'kyc_status' => 'pending',
    ]);
}

function verifyAllPartnerKyc(VendorProfile $profile, User $admin): void
{
    Storage::fake('private');
    $action = app(VendorPartnerKycAction::class);
    foreach (VendorProfile::requiredMediaTypes() as $type) {
        $action->uploadDocument($profile, $type, UploadedFile::fake()->image("{$type}.jpg"), null, $admin);
        $action->verifyDocument($profile, $type, 'verified', null, $admin);
    }
}

it('adds a vendor partner as pending activation with KYC pending', function () {
    $admin = userWithPermissions(['vendor-partners.view', 'vendor-partners.create'], scope: 'all');

    $this->actingAs($admin)->post('/admin/vendor-partners', [
        'name' => 'Imran Khan', 'email' => 'imran@partner.test', 'password' => 'Password123!',
        'phone' => '9811100000', 'company_name' => 'IK Motors', 'city' => 'Delhi',
    ])->assertRedirect();

    $user = User::query()->where('email', 'imran@partner.test')->firstOrFail();
    expect($user->hasRole('Vendor Partner'))->toBeTrue()
        ->and($user->vendorProfile->status)->toBe(VendorProfileStatus::PendingActivation)
        ->and($user->vendorProfile->kyc_status)->toBe('pending');
});

it('forbids adding a vendor partner without the create permission', function () {
    $user = userWithPermissions(['vendor-partners.view'], scope: 'all');

    $this->actingAs($user)->post('/admin/vendor-partners', [
        'name' => 'X', 'email' => 'x@p.test', 'password' => 'Password123!',
    ])->assertForbidden();

    expect(User::query()->where('email', 'x@p.test')->exists())->toBeFalse();
});

it('validates a unique email when adding a partner', function () {
    $admin = userWithPermissions(['vendor-partners.create'], scope: 'all');
    User::factory()->create(['email' => 'dupe@p.test']);

    $this->actingAs($admin)->post('/admin/vendor-partners', [
        'name' => 'Y', 'email' => 'dupe@p.test', 'password' => 'Password123!',
    ])->assertSessionHasErrors('email');
});

it('lets an admin update a partner and keeps the password when blank', function () {
    $admin = userWithPermissions(['vendor-partners.update'], scope: 'all');
    $profile = makePartner('old@p.test');
    $profile->user->update(['name' => 'Old Name']);
    $originalHash = $profile->user->password;

    $this->actingAs($admin)->patch("/admin/vendor-partners/{$profile->id}", [
        'name' => 'New Name', 'email' => 'new@p.test', 'password' => '',
        'company_name' => 'New Co', 'city' => 'Mumbai',
    ])->assertRedirect('/admin/vendor-partners');

    $profile->refresh();
    expect($profile->user->name)->toBe('New Name')
        ->and($profile->user->email)->toBe('new@p.test')
        ->and($profile->user->password)->toBe($originalHash)
        ->and($profile->company_name)->toBe('New Co');
});

it('lets an admin upload a partner KYC document', function () {
    Storage::fake('private');
    $admin = userWithPermissions(['vendor-partners.update'], scope: 'all');
    $profile = makePartner();

    $this->actingAs($admin)->post("/admin/vendor-partners/{$profile->id}/documents", [
        'type' => 'pan', 'file' => UploadedFile::fake()->image('pan.jpg'), 'number' => 'ABCDE1234F',
    ])->assertRedirect();

    $doc = $profile->documents()->where('type', 'pan')->firstOrFail();
    expect($doc->status)->toBe('pending')
        ->and($doc->number)->toBe('ABCDE1234F')
        // A single doc doesn't complete the required set.
        ->and($profile->fresh()->kyc_status)->toBe('pending');
});

it('marks KYC verified once every required document is verified', function () {
    $admin = userWithPermissions(['vendor-partners.update'], scope: 'all');
    $profile = makePartner();

    verifyAllPartnerKyc($profile, $admin);

    expect($profile->fresh()->kyc_status)->toBe('verified');
});

it('blocks activating a partner until KYC is verified', function () {
    $admin = userWithPermissions(['vendor-partners.activate'], scope: 'all');
    $profile = makePartner();

    $this->actingAs($admin)
        ->post("/admin/vendor-partners/{$profile->id}/status", ['status' => 'active'])
        ->assertSessionHasErrors('status');

    expect($profile->fresh()->status)->toBe(VendorProfileStatus::PendingActivation);
});

it('activates a partner once KYC is verified', function () {
    $admin = userWithPermissions(['vendor-partners.activate', 'vendor-partners.update'], scope: 'all');
    $profile = makePartner();
    verifyAllPartnerKyc($profile, $admin);

    $this->actingAs($admin)
        ->post("/admin/vendor-partners/{$profile->id}/status", ['status' => 'active'])
        ->assertSessionHasNoErrors();

    expect($profile->fresh()->status)->toBe(VendorProfileStatus::Active);
});

it('lets the vendor partner upload their own KYC document', function () {
    Storage::fake('private');
    $profile = makePartner('self@p.test');

    $this->actingAs($profile->user)
        ->post('/vendor/kyc/documents', ['type' => 'aadhaar_front', 'file' => UploadedFile::fake()->image('a.jpg')])
        ->assertRedirect();

    expect($profile->documents()->where('type', 'aadhaar_front')->exists())->toBeTrue();
});

it('streams a partner KYC document to its owner but not to a stranger', function () {
    Storage::fake('private');
    $admin = userWithPermissions(['vendor-partners.update'], scope: 'all');
    $profile = makePartner('owner@p.test');
    $doc = app(VendorPartnerKycAction::class)->uploadDocument($profile, 'photo', UploadedFile::fake()->image('p.jpg'), null, $admin);

    $this->actingAs($profile->user)->get("/vendor-partner-document/{$doc->id}")->assertOk();

    $stranger = User::factory()->create();
    $stranger->assignRole('Vendor Partner');
    $this->actingAs($stranger)->get("/vendor-partner-document/{$doc->id}")->assertForbidden();
});
