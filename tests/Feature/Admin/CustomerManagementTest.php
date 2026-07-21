<?php

use App\Domain\Customers\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function kycCustomer(): Customer
{
    return Customer::query()->create([
        'customer_code' => 'CUST-'.fake()->unique()->numerify('####'),
        'name' => 'Doc Cust', 'mobile' => '98'.fake()->unique()->numerify('########'), 'kyc_status' => 'pending',
    ]);
}

it('lets a permitted user add a customer', function () {
    $user = userWithPermissions(['customers.view', 'customers.create'], scope: 'all');

    $response = $this->actingAs($user)->post('/admin/customers', [
        'name' => 'Anita Desai', 'father_name' => 'Ramesh Desai', 'mobile' => '9800012345',
        'email' => 'anita@example.test', 'city' => 'Pune', 'state' => 'Maharashtra', 'occupation' => 'Teacher',
    ]);

    $customer = Customer::query()->where('mobile', '9800012345')->firstOrFail();
    $response->assertRedirect("/admin/customers/{$customer->id}");

    expect($customer->name)->toBe('Anita Desai')
        ->and($customer->father_name)->toBe('Ramesh Desai')
        ->and($customer->customer_code)->not->toBeEmpty()
        ->and($customer->kyc_status)->toBe('pending')
        ->and($customer->city)->toBe('Pune');
});

it('forbids adding a customer without the create permission', function () {
    $user = userWithPermissions(['customers.view'], scope: 'all');

    $this->actingAs($user)
        ->post('/admin/customers', ['name' => 'X', 'mobile' => '9000000000'])
        ->assertForbidden();

    expect(Customer::query()->where('mobile', '9000000000')->exists())->toBeFalse();
});

it('keeps one customer per mobile when adding', function () {
    $user = userWithPermissions(['customers.create'], scope: 'all');
    Customer::query()->create(['customer_code' => 'CUST-DUP', 'name' => 'Existing', 'mobile' => '9811122233', 'kyc_status' => 'pending']);

    $this->actingAs($user)
        ->post('/admin/customers', ['name' => 'Another', 'mobile' => '9811122233'])
        ->assertSessionHasErrors('mobile');

    expect(Customer::query()->where('mobile', '9811122233')->count())->toBe(1);
});

it('saves Aadhaar and PAN for a KYC-permitted user', function () {
    $user = userWithPermissions(['customers.create', 'customers.view-kyc'], scope: 'all');

    $this->actingAs($user)->post('/admin/customers', [
        'name' => 'Ravi', 'mobile' => '9820011111',
        'aadhaar_number' => '123456789012', 'pan_number' => 'abcde1234f',
    ])->assertRedirect();

    $customer = Customer::query()->where('mobile', '9820011111')->firstOrFail();
    expect($customer->aadhaar_number)->toBe('123456789012')
        // PAN is upper-cased on save.
        ->and($customer->pan_number)->toBe('ABCDE1234F');
});

it('validates Aadhaar and PAN formats', function () {
    $user = userWithPermissions(['customers.create', 'customers.view-kyc'], scope: 'all');

    $this->actingAs($user)->post('/admin/customers', [
        'name' => 'Bad', 'mobile' => '9820022222',
        'aadhaar_number' => '12345', 'pan_number' => 'NOTAPAN',
    ])->assertSessionHasErrors(['aadhaar_number', 'pan_number']);
});

it('ignores identity numbers from a user without KYC access', function () {
    $user = userWithPermissions(['customers.create'], scope: 'all');

    $this->actingAs($user)->post('/admin/customers', [
        'name' => 'NoKyc', 'mobile' => '9820033333',
        'aadhaar_number' => '123456789012', 'pan_number' => 'ABCDE1234F',
    ])->assertRedirect();

    $customer = Customer::query()->where('mobile', '9820033333')->firstOrFail();
    expect($customer->aadhaar_number)->toBeNull()
        ->and($customer->pan_number)->toBeNull();
});

it('hides identity numbers on the detail page from a non-KYC user', function () {
    $user = userWithPermissions(['customers.view'], scope: 'all');
    $customer = Customer::query()->create([
        'customer_code' => 'CUST-KYC', 'name' => 'Secret', 'mobile' => '9820044444',
        'kyc_status' => 'pending', 'aadhaar_number' => '123456789012', 'pan_number' => 'ABCDE1234F',
    ]);

    $this->actingAs($user)
        ->get("/admin/customers/{$customer->id}")
        ->assertInertia(fn ($p) => $p->missing('customer.aadhaar_number')->missing('customer.pan_number'));
});

it('requires a name and mobile', function () {
    $user = userWithPermissions(['customers.create'], scope: 'all');

    $this->actingAs($user)
        ->post('/admin/customers', [])
        ->assertSessionHasErrors(['name', 'mobile']);
});

it('lets a permitted user edit a customer', function () {
    $user = userWithPermissions(['customers.view', 'customers.update'], scope: 'all');
    $customer = Customer::query()->create(['customer_code' => 'CUST-1', 'name' => 'Old', 'mobile' => '9812345678', 'kyc_status' => 'pending']);

    $this->actingAs($user)
        ->patch("/admin/customers/{$customer->id}", ['name' => 'New Name', 'mobile' => '9812345678', 'city' => 'Nagpur'])
        ->assertRedirect("/admin/customers/{$customer->id}");

    $customer->refresh();
    expect($customer->name)->toBe('New Name')
        ->and($customer->city)->toBe('Nagpur');
});

it('forbids editing a customer without the update permission', function () {
    $user = userWithPermissions(['customers.view'], scope: 'all');
    $customer = Customer::query()->create(['customer_code' => 'CUST-2', 'name' => 'Nope', 'mobile' => '9800000001', 'kyc_status' => 'pending']);

    $this->actingAs($user)
        ->patch("/admin/customers/{$customer->id}", ['name' => 'Hacked', 'mobile' => '9800000001'])
        ->assertForbidden();
});

it('uploads a customer KYC document and sets the status to partial', function () {
    Storage::fake('private');
    $user = userWithPermissions(['customers.view', 'customers.view-kyc'], scope: 'all');
    $customer = kycCustomer();

    $this->actingAs($user)->post("/admin/customers/{$customer->id}/documents", [
        'type' => 'aadhaar', 'file' => UploadedFile::fake()->image('aadhaar.jpg'), 'number' => '123456789012',
    ])->assertRedirect();

    $doc = $customer->documents()->where('type', 'aadhaar')->firstOrFail();
    expect($doc->status)->toBe('received')
        ->and($doc->file_path)->not->toBeNull()
        ->and($customer->fresh()->kyc_status)->toBe('partial');
    Storage::disk('private')->assertExists($doc->file_path);
});

it('marks a customer KYC-verified once required documents are verified', function () {
    Storage::fake('private');
    $user = userWithPermissions(['customers.view', 'customers.view-kyc'], scope: 'all');
    $customer = kycCustomer();

    foreach (Customer::requiredKycTypes() as $type) {
        $this->actingAs($user)->post("/admin/customers/{$customer->id}/documents", [
            'type' => $type, 'file' => UploadedFile::fake()->image("{$type}.jpg"),
        ])->assertRedirect();
        $this->actingAs($user)->post("/admin/customers/{$customer->id}/documents/verify", [
            'type' => $type, 'status' => 'verified',
        ])->assertRedirect();
    }

    expect($customer->fresh()->kyc_status)->toBe('verified');
});

it('forbids uploading a KYC document without the view-kyc permission', function () {
    Storage::fake('private');
    $user = userWithPermissions(['customers.view', 'customers.update'], scope: 'all');
    $customer = kycCustomer();

    $this->actingAs($user)
        ->post("/admin/customers/{$customer->id}/documents", ['type' => 'pan', 'file' => UploadedFile::fake()->image('p.jpg')])
        ->assertForbidden();

    expect($customer->documents()->count())->toBe(0);
});

it('streams a customer document only to a KYC-permitted user', function () {
    Storage::fake('private');
    $kycUser = userWithPermissions(['customers.view', 'customers.view-kyc'], scope: 'all');
    $customer = kycCustomer();
    $this->actingAs($kycUser)->post("/admin/customers/{$customer->id}/documents", [
        'type' => 'pan', 'file' => UploadedFile::fake()->image('p.jpg'),
    ]);
    $doc = $customer->documents()->where('type', 'pan')->firstOrFail();

    $this->actingAs($kycUser)->get("/admin/customer-documents/{$doc->id}")->assertOk();

    $plainUser = userWithPermissions(['customers.view'], scope: 'all');
    $this->actingAs($plainUser)->get("/admin/customer-documents/{$doc->id}")->assertForbidden();
});
