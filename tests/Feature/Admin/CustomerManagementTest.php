<?php

use App\Domain\Customers\Models\Customer;

it('lets a permitted user add a customer', function () {
    $user = userWithPermissions(['customers.view', 'customers.create'], scope: 'all');

    $response = $this->actingAs($user)->post('/admin/customers', [
        'name' => 'Anita Desai', 'mobile' => '9800012345', 'email' => 'anita@example.test',
        'city' => 'Pune', 'state' => 'Maharashtra', 'occupation' => 'Teacher',
    ]);

    $customer = Customer::query()->where('mobile', '9800012345')->firstOrFail();
    $response->assertRedirect("/admin/customers/{$customer->id}");

    expect($customer->name)->toBe('Anita Desai')
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
