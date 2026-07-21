<?php

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Actions\CreateStockFromPurchaseAction;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PurchaseApprovals\Actions\CompletePurchaseFromApprovalAction;
use App\Domain\PurchaseApprovals\Actions\RequestPurchaseApprovalAction;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Models\ApprovalLimit;
use App\Domain\RolesPermissions\Models\Role;
use App\Domain\Valuations\Actions\SaveValuationAction;
use App\Domain\VehiclePurchases\Actions\ConfirmPossessionAction;
use App\Domain\VehiclePurchases\Actions\RecordSellerPaymentAction;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;

/**
 * Build the purchase-approval chain roles + limits used by the engine.
 */
function seedApprovalChain(): void
{
    foreach ([
        ['Purchase Manager', 300000.00],
        ['Branch Manager', 800000.00],
        ['Director', null],
    ] as [$name, $limit]) {
        $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        ApprovalLimit::query()->updateOrCreate(
            ['role_id' => $role->id, 'module' => 'purchase-approval'],
            ['max_amount' => $limit, 'requires_escalation' => $limit !== null],
        );
    }
}

it('computes and stores valuation profit metrics', function () {
    $user = superAdmin();
    $lead = PurchaseLead::factory()->create();

    $valuation = app(SaveValuationAction::class)->execute($lead, [
        'expected_retail_price' => 600000,
        'repair_estimate' => 40000,
        'rto_expense' => 15000,
        'documentation_expense' => 5000,
        'target_profit' => 50000,
    ], $user);

    expect((float) $valuation->recommended_price)->toBe(490000.0)
        ->and((float) $valuation->expected_net_profit)->toBe(50000.0);
});

it('escalates approval to Director when amount exceeds a manager limit', function () {
    seedApprovalChain();
    $manager = superAdmin();
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::Negotiation]);

    // 500000 is above Purchase Manager (300k) but within Branch Manager (800k).
    $request = app(RequestPurchaseApprovalAction::class)->execute($lead, 500000, $manager);

    $roleNames = $request->steps->map(fn ($s) => $s->role->name)->all();

    expect($roleNames)->toBe(['Purchase Manager', 'Branch Manager'])
        ->and($lead->fresh()->status)->toBe(PurchaseLeadStatus::PurchaseApprovalPending);
});

it('runs the full purchase lifecycle through to automatic stock entry', function () {
    seedApprovalChain();
    $actor = superAdmin();

    $branch = Branch::factory()->create();
    $lead = PurchaseLead::factory()->create([
        'status' => PurchaseLeadStatus::Negotiation,
        'branch_id' => $branch->id,
        'registration_number' => 'UP32AB1234',
        'make' => 'Maruti',
        'model' => 'Swift',
    ]);

    app(SaveValuationAction::class)->execute($lead, [
        'expected_retail_price' => 600000,
        'repair_estimate' => 20000,
        'rto_expense' => 10000,
        'documentation_expense' => 5000,
        'target_profit' => 40000,
        'final_negotiated_price' => 250000,
    ], $actor);

    // 250k is within the Purchase Manager limit (300k) → single-step approval.
    $request = app(RequestPurchaseApprovalAction::class)->execute($lead->fresh(), 250000, $actor);
    expect($request->steps)->toHaveCount(1);

    $approved = app(ApprovalEngine::class)->approve($request, $actor, 250000);
    expect($approved->status->value)->toBe('approved');

    $purchase = app(CompletePurchaseFromApprovalAction::class)->execute($approved, $actor);
    expect($purchase->purchase_number)->toStartWith('PUR-')
        ->and((float) $purchase->agreed_price)->toBe(250000.0)
        ->and($lead->fresh()->status)->toBe(PurchaseLeadStatus::AgreementPending);

    // Payment maker-checker.
    $payments = app(RecordSellerPaymentAction::class);
    $payment = $payments->create($purchase, ['type' => 'full', 'amount' => 250000], $actor);
    expect($payment->status)->toBe('pending_approval');

    // Possession → automatic stock entry.
    $result = app(ConfirmPossessionAction::class)->execute($purchase->fresh(), [
        'vehicle_received' => true,
        'original_rc_received' => true,
        'main_key' => true,
    ], $actor);

    $vehicle = $result['vehicle'];

    expect($vehicle)->toBeInstanceOf(Vehicle::class)
        ->and($vehicle->stock_number)->toStartWith('STK-')
        ->and($vehicle->status)->toBe(VehicleStatus::InStock)
        ->and((float) $vehicle->landed_cost)->toBe(265000.0) // 250000 + 15000 initial expenses (rto 10k + doc 5k)
        ->and($vehicle->registration_number)->toBe('UP32AB1234')
        ->and($lead->fresh()->status)->toBe(PurchaseLeadStatus::Purchased)
        ->and($purchase->fresh()->vehicle_id)->toBe($vehicle->id);

    $this->assertDatabaseHas('vehicle_status_histories', ['vehicle_id' => $vehicle->id, 'to_status' => 'in_stock']);
});

it('blocks duplicate stock creation by registration number', function () {
    $actor = superAdmin();
    $lead = PurchaseLead::factory()->create(['registration_number' => 'DL01XY9999']);

    Vehicle::query()->create([
        'stock_number' => 'STK-2026-000099',
        'registration_number' => 'DL01XY9999',
        'make' => 'Honda', 'model' => 'City',
        'status' => VehicleStatus::InStock->value,
    ]);

    $purchase = VehiclePurchase::query()->create([
        'purchase_number' => 'PUR-2026-000099',
        'purchase_lead_id' => $lead->id,
        'agreed_price' => 500000,
        'status' => 'possession_pending',
    ]);

    app(CreateStockFromPurchaseAction::class)->execute($purchase, $actor);
})->throws(RuntimeException::class, 'already exists');

it('enforces maker-checker on seller payments', function () {
    $maker = User::factory()->create();
    $lead = PurchaseLead::factory()->create();
    $purchase = VehiclePurchase::query()->create([
        'purchase_number' => 'PUR-2026-000010',
        'purchase_lead_id' => $lead->id,
        'agreed_price' => 400000,
        'status' => 'payment_pending',
    ]);

    $payments = app(RecordSellerPaymentAction::class);
    $payment = $payments->create($purchase, ['type' => 'advance', 'amount' => 100000], $maker);

    // Maker cannot approve their own payment.
    expect(fn () => $payments->approve($payment, $maker))
        ->toThrow(RuntimeException::class, 'maker cannot approve');
});

it('reverses a paid seller payment with an offsetting entry', function () {
    $maker = User::factory()->create();
    $checker = superAdmin();
    $lead = PurchaseLead::factory()->create();
    $purchase = VehiclePurchase::query()->create([
        'purchase_number' => 'PUR-2026-000011',
        'purchase_lead_id' => $lead->id,
        'agreed_price' => 400000,
        'status' => 'payment_pending',
    ]);

    $payments = app(RecordSellerPaymentAction::class);
    $payment = $payments->create($purchase, ['type' => 'advance', 'amount' => 100000], $maker);
    $payments->approve($payment, $checker);

    $reversal = $payments->reverse($payment->fresh(), $checker, 'Wrong account');

    expect((float) $reversal->amount)->toBe(-100000.0)
        ->and($reversal->reversal_of)->toBe($payment->id)
        ->and($payment->fresh()->status)->toBe('reversed')
        ->and((float) $purchase->fresh()->paidAmount())->toBe(0.0);
});

it('refuses to reach Purchased through the generic status control (must use Confirm Possession)', function () {
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create([
        'status' => PurchaseLeadStatus::PossessionPending->value,
        'registration_number' => 'UP99 QA0001',
    ]);

    $this->actingAs($admin)
        ->from("/admin/purchase-leads/{$lead->id}")
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'purchased'])
        ->assertRedirect("/admin/purchase-leads/{$lead->id}")
        ->assertSessionHas('error');

    expect($lead->fresh()->status)->toBe(PurchaseLeadStatus::PossessionPending)
        ->and(Vehicle::query()->where('registration_number', 'UP99 QA0001')->exists())->toBeFalse();
});

it('refuses to reach PurchaseApproved through the generic status control', function () {
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create([
        'status' => PurchaseLeadStatus::PurchaseApprovalPending->value,
    ]);

    $this->actingAs($admin)
        ->from("/admin/purchase-leads/{$lead->id}")
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'purchase_approved'])
        ->assertSessionHas('error');

    expect($lead->fresh()->status)->toBe(PurchaseLeadStatus::PurchaseApprovalPending);
});

it('omits the dedicated-action statuses from the generic transition dropdown', function () {
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create([
        'status' => PurchaseLeadStatus::PossessionPending->value,
    ]);

    $this->actingAs($admin)
        ->get("/admin/purchase-leads/{$lead->id}")
        ->assertInertia(fn ($p) => $p->where('allowedTransitions', fn ($t) => collect($t)->pluck('value')->doesntContain('purchased')));
});

it('surfaces the lead pending purchase approval on the workbench so it can be decided', function () {
    seedApprovalChain();
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create();
    $request = app(RequestPurchaseApprovalAction::class)->execute($lead, 250000, $admin);

    $this->actingAs($admin)
        ->get("/admin/purchase-leads/{$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('approvalRequest.id', $request->id)
            ->where('approvalRequest.status', 'pending')
            ->has('approvalRequest.steps'));
});

it('refuses a duplicate purchase approval request for the same lead', function () {
    seedApprovalChain();
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create();
    app(RequestPurchaseApprovalAction::class)->execute($lead, 250000, $admin);

    expect(fn () => app(RequestPurchaseApprovalAction::class)->execute($lead->fresh(), 250000, $admin))
        ->toThrow(RuntimeException::class, 'already pending');

    expect(ApprovalRequest::query()
        ->where('module', 'purchase-approval')
        ->where('subject_id', $lead->id)
        ->count())->toBe(1);
});

it('refuses to reach PurchaseApprovalPending through the generic status control', function () {
    $admin = superAdmin();
    $lead = PurchaseLead::factory()->create(['status' => PurchaseLeadStatus::Negotiation->value]);

    $this->actingAs($admin)
        ->from("/admin/purchase-leads/{$lead->id}")
        ->post("/admin/purchase-leads/{$lead->id}/transition", ['status' => 'purchase_approval_pending'])
        ->assertSessionHas('error');

    expect($lead->fresh()->status)->toBe(PurchaseLeadStatus::Negotiation);
});
