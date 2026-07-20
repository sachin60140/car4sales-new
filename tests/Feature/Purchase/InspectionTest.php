<?php

use App\Domain\Inspections\Enums\InspectionStatus;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Database\Seeders\InspectionChecklistSeeder;

beforeEach(function () {
    $this->seed(InspectionChecklistSeeder::class);
});

it('creates an inspection with sections from the checklist and schedules the lead', function () {
    $user = userWithPermissions(['inspections.view', 'inspections.create', 'purchase-leads.view'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::Contacted]);

    $this->actingAs($user)
        ->post('/admin/inspections', ['purchase_lead_id' => $lead->id])
        ->assertRedirect();

    $inspection = VehicleInspection::query()->first();

    expect($inspection)->not->toBeNull()
        ->and($inspection->inspection_number)->toStartWith('INS-')
        ->and($inspection->sections()->count())->toBeGreaterThan(0)
        ->and($lead->fresh()->status)->toBe(PurchaseLeadStatus::InspectionScheduled);
});

it('submits and locks an inspection, totalling repair estimates', function () {
    $user = userWithPermissions(['inspections.view', 'inspections.create', 'inspections.update', 'purchase-leads.view'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::Contacted]);

    $this->actingAs($user)->post('/admin/inspections', ['purchase_lead_id' => $lead->id]);
    $inspection = VehicleInspection::query()->first();

    $section = $inspection->sections()->first();
    $this->actingAs($user)->put("/admin/inspections/{$inspection->id}", [
        'overall_grade' => 'B',
        'sections' => [['id' => $section->id, 'status' => 'fail', 'repair_estimate' => 12000]],
    ])->assertRedirect();

    $this->actingAs($user)
        ->post("/admin/inspections/{$inspection->id}/submit", ['result' => 'recommended_with_repairs'])
        ->assertRedirect();

    $inspection->refresh();

    expect($inspection->status)->toBe(InspectionStatus::Submitted)
        ->and($inspection->isLocked())->toBeTrue()
        ->and((float) $inspection->total_repair_estimate)->toBe(12000.0)
        ->and($lead->fresh()->status)->toBe(PurchaseLeadStatus::InspectionCompleted);
});

it('prevents editing a locked inspection', function () {
    $user = userWithPermissions(['inspections.view', 'inspections.create', 'inspections.update', 'purchase-leads.view'], scope: 'all');
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::Contacted]);

    $this->actingAs($user)->post('/admin/inspections', ['purchase_lead_id' => $lead->id]);
    $inspection = VehicleInspection::query()->first();
    $this->actingAs($user)->post("/admin/inspections/{$inspection->id}/submit", ['result' => 'recommended']);

    $this->actingAs($user)
        ->put("/admin/inspections/{$inspection->id}", ['overall_grade' => 'A'])
        ->assertForbidden();
});
