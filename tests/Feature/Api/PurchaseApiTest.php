<?php

use App\Domain\PurchaseLeads\Models\PurchaseLead;

function apiToken(array $permissions): string
{
    $user = userWithPermissions([...$permissions, 'access-mobile']);

    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ])->json('data.token');
}

it('lists purchase leads through the mobile api in the envelope', function () {
    $token = apiToken(['purchase-leads.view']);
    PurchaseLead::factory()->count(3)->create();

    $this->getJson('/api/v1/purchase-leads', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'last_page']]]);
});

it('creates a purchase lead through the mobile api', function () {
    $token = apiToken(['purchase-leads.view', 'purchase-leads.create']);

    $this->postJson('/api/v1/purchase-leads', [
        'seller_name' => 'Mobile Seller',
        'mobile' => '9812345678',
        'make' => 'Tata',
        'model' => 'Nexon',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(201)
        ->assertJsonPath('data.lead_number', fn ($n) => str_starts_with($n, 'PL-'));

    $this->assertDatabaseHas('purchase_leads', ['seller_name' => 'Mobile Seller', 'source' => 'mobile']);
});

it('denies lead creation without permission on the api', function () {
    $token = apiToken(['purchase-leads.view']);

    $this->postJson('/api/v1/purchase-leads', [
        'seller_name' => 'X', 'mobile' => '9000000000',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

it('adds a follow-up via the api', function () {
    $token = apiToken(['purchase-leads.view', 'purchase-leads.update']);
    $lead = PurchaseLead::factory()->create();

    $this->postJson("/api/v1/purchase-leads/{$lead->id}/followups", [
        'contact_mode' => 'call',
        'remarks' => 'Spoke to seller',
        'next_follow_up_at' => now()->addDay()->toIso8601String(),
    ], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(201);

    $this->assertDatabaseHas('purchase_followups', ['purchase_lead_id' => $lead->id, 'contact_mode' => 'call']);
});

it('rejects an invalid transition via the api with 422', function () {
    $token = apiToken(['purchase-leads.view', 'purchase-leads.update']);
    $lead = PurchaseLead::factory()->create();

    $this->postJson("/api/v1/purchase-leads/{$lead->id}/transition", [
        'status' => 'purchased',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
