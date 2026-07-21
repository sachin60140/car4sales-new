<?php

use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lets an admin add a vendor partner', function () {
    $admin = userWithPermissions(['vendor-partners.view', 'vendor-partners.create'], scope: 'all');

    $this->actingAs($admin)->post('/admin/vendor-partners', [
        'name' => 'Imran Khan', 'email' => 'imran@partner.test', 'password' => 'Password123!',
        'phone' => '9811100000', 'company_name' => 'IK Motors', 'city' => 'Delhi',
        'gst_number' => '07ABCDE1234F1Z5', 'status' => VendorProfileStatus::Active->value,
    ])->assertRedirect('/admin/vendor-partners');

    $user = User::query()->where('email', 'imran@partner.test')->firstOrFail();
    expect($user->hasRole('Vendor Partner'))->toBeTrue()
        ->and($user->vendorProfile->company_name)->toBe('IK Motors')
        ->and($user->vendorProfile->status)->toBe(VendorProfileStatus::Active)
        ->and($user->vendorProfile->activated_by)->toBe($admin->id);
});

it('can add a partner in a pending state', function () {
    $admin = userWithPermissions(['vendor-partners.create'], scope: 'all');

    $this->actingAs($admin)->post('/admin/vendor-partners', [
        'name' => 'Pending Pat', 'email' => 'pending@partner.test', 'password' => 'Password123!',
        'status' => VendorProfileStatus::PendingActivation->value,
    ])->assertRedirect();

    $profile = User::query()->where('email', 'pending@partner.test')->firstOrFail()->vendorProfile;
    expect($profile->status)->toBe(VendorProfileStatus::PendingActivation)
        ->and($profile->activated_at)->toBeNull();
});

it('forbids adding a vendor partner without the create permission', function () {
    $user = userWithPermissions(['vendor-partners.view'], scope: 'all');

    $this->actingAs($user)->post('/admin/vendor-partners', [
        'name' => 'X', 'email' => 'x@p.test', 'password' => 'Password123!',
        'status' => VendorProfileStatus::Active->value,
    ])->assertForbidden();

    expect(User::query()->where('email', 'x@p.test')->exists())->toBeFalse();
});

it('validates a unique email when adding a partner', function () {
    $admin = userWithPermissions(['vendor-partners.create'], scope: 'all');
    User::factory()->create(['email' => 'dupe@p.test']);

    $this->actingAs($admin)->post('/admin/vendor-partners', [
        'name' => 'Y', 'email' => 'dupe@p.test', 'password' => 'Password123!',
        'status' => VendorProfileStatus::Active->value,
    ])->assertSessionHasErrors('email');
});

it('lets an admin update a partner and keeps the password when blank', function () {
    $admin = userWithPermissions(['vendor-partners.update'], scope: 'all');

    $partnerUser = User::factory()->create(['name' => 'Old Name', 'email' => 'old@p.test']);
    $partnerUser->assignRole('Vendor Partner');
    $profile = $partnerUser->vendorProfile()->create([
        'company_name' => 'Old Co', 'contact_person' => 'Old Name', 'status' => VendorProfileStatus::Active->value,
    ]);
    $originalHash = $partnerUser->password;

    $this->actingAs($admin)->patch("/admin/vendor-partners/{$profile->id}", [
        'name' => 'New Name', 'email' => 'new@p.test', 'password' => '',
        'company_name' => 'New Co', 'city' => 'Mumbai',
    ])->assertRedirect('/admin/vendor-partners');

    $partnerUser->refresh();
    $profile->refresh();
    expect($partnerUser->name)->toBe('New Name')
        ->and($partnerUser->email)->toBe('new@p.test')
        ->and($partnerUser->password)->toBe($originalHash)
        ->and($profile->company_name)->toBe('New Co')
        ->and($profile->city)->toBe('Mumbai')
        // Status is unchanged by a profile edit.
        ->and($profile->status)->toBe(VendorProfileStatus::Active);
});

it('forbids updating a partner without the update permission', function () {
    $user = userWithPermissions(['vendor-partners.view'], scope: 'all');
    $partnerUser = User::factory()->create();
    $partnerUser->assignRole('Vendor Partner');
    $profile = $partnerUser->vendorProfile()->create(['status' => VendorProfileStatus::Active->value]);

    $this->actingAs($user)
        ->patch("/admin/vendor-partners/{$profile->id}", ['name' => 'Nope', 'email' => 'nope@p.test'])
        ->assertForbidden();
});
