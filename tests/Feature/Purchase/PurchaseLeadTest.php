<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\VehicleVerification\Services\VerificationChecklist;

it('creates a purchase lead with a sequential number and seeded verification checklist', function () {
    $user = userWithPermissions(['purchase-leads.view', 'purchase-leads.create']);
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->post('/admin/purchase-leads', [
            'seller_name' => 'Amit Sharma',
            'mobile' => '9876543210',
            'make' => 'Maruti',
            'model' => 'Swift',
            'branch_id' => $branch->id,
        ])
        ->assertRedirect();

    $lead = PurchaseLead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->lead_number)->toStartWith('PL-')
        ->and($lead->status)->toBe(PurchaseLeadStatus::New)
        ->and($lead->verifications()->count())->toBe(count(VerificationChecklist::TYPES));

    $this->assertDatabaseHas('purchase_lead_status_histories', [
        'purchase_lead_id' => $lead->id,
        'to_status' => 'new',
    ]);
});

it('forbids creating a lead without permission', function () {
    $user = userWithPermissions(['purchase-leads.view']);

    $this->actingAs($user)
        ->post('/admin/purchase-leads', ['seller_name' => 'X', 'mobile' => '9000000000'])
        ->assertForbidden();
});

it('allows a valid status transition and records history', function () {
    $user = userWithPermissions(['purchase-leads.view', 'purchase-leads.update'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::New]);

    $this->actingAs($user)
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'contacted'])
        ->assertRedirect();

    expect($lead->fresh()->status)->toBe(PurchaseLeadStatus::Contacted);
    $this->assertDatabaseHas('purchase_lead_status_histories', ['purchase_lead_id' => $lead->id, 'to_status' => 'contacted']);
});

it('rejects an invalid status transition', function () {
    $user = userWithPermissions(['purchase-leads.view', 'purchase-leads.update'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::New]);

    // 'negotiation' is not reachable from New (and isn't a guarded milestone),
    // so it exercises the invalid-transition path rather than the record-action guard.
    $this->actingAs($user)
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'negotiation'])
        ->assertStatus(422);

    expect($lead->fresh()->status)->toBe(PurchaseLeadStatus::New);
});

it('requires a lost reason when marking a lead lost', function () {
    $user = userWithPermissions(['purchase-leads.view', 'purchase-leads.update'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::New]);

    $this->actingAs($user)
        ->from("/admin/purchase-leads/{$lead->id}")
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'rejected'])
        ->assertSessionHasErrors('lost_reason');
});

it('scopes leads to the user branch under own_branch scope', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $user = userWithPermissions(['purchase-leads.view'], scope: 'own_branch', attributes: ['branch_id' => $branchA->id]);
    PurchaseLead::factory()->create(['branch_id' => $branchA->id]);
    $other = PurchaseLead::factory()->create(['branch_id' => $branchB->id]);

    $this->actingAs($user)->get("/admin/purchase-leads/{$other->id}")->assertForbidden();
});
