<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\SalesLeads\Actions\AssignLeadAction;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;

function makeLead(array $data = [], ?User $creator = null): SalesLead
{
    return app(CreateSalesLeadAction::class)->execute(array_merge([
        'name' => 'Buyer', 'mobile' => '90000'.fake()->unique()->numerify('#####'), 'source' => 'manual',
    ], $data), $creator ?? superAdmin());
}

it('creates a sales lead with a unified customer record', function () {
    $lead = makeLead(['name' => 'Anita', 'mobile' => '9000012345', 'city' => 'Lucknow']);

    expect($lead->lead_number)->toStartWith('SL-')
        ->and($lead->status)->toBe(SalesLeadStatus::New)
        ->and($lead->customer_id)->not->toBeNull()
        ->and($lead->customer->customer_code)->toStartWith('CUST-')
        ->and($lead->customer->mobile)->toBe('9000012345');
});

it('reuses the same customer for repeat leads from one mobile', function () {
    makeLead(['mobile' => '9000099999']);
    makeLead(['mobile' => '9000099999']);

    expect(Customer::query()->where('mobile', '9000099999')->count())->toBe(1)
        ->and(SalesLead::query()->count())->toBe(2);
});

it('moves New to Assigned on first telecaller assignment', function () {
    $tc = User::factory()->create();
    $lead = makeLead();

    app(AssignLeadAction::class)->execute($lead, 'telecaller', $tc->id, superAdmin());

    expect($lead->fresh()->status)->toBe(SalesLeadStatus::Assigned)
        ->and($lead->fresh()->telecaller_id)->toBe($tc->id);
    $this->assertDatabaseHas('lead_assignments', ['sales_lead_id' => $lead->id, 'role' => 'telecaller', 'to_user_id' => $tc->id]);
});

it('scopes the queue so a telecaller sees only their assigned leads', function () {
    $branch = Branch::factory()->create();
    $tc = userWithPermissions(['sales-leads.view'], scope: 'assigned', attributes: ['branch_id' => $branch->id]);

    $mine = makeLead(['branch_id' => $branch->id]);
    $mine->update(['telecaller_id' => $tc->id]);
    makeLead(['branch_id' => $branch->id]); // someone else's

    $this->actingAs($tc)->get('/admin/sales-leads')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('leads.data', 1)->where('leads.data.0.id', $mine->id));
});

it('requires a lost reason when transitioning to a lost status via the web', function () {
    $manager = userWithPermissions(['sales-leads.view', 'sales-leads.update'], scope: 'all');
    $lead = makeLead();

    $this->actingAs($manager)
        ->from("/admin/sales-leads/{$lead->id}")
        ->post("/admin/sales-leads/{$lead->id}/transition", ['status' => 'lost'])
        ->assertSessionHasErrors('lost_reason_id');

    expect($lead->fresh()->status)->toBe(SalesLeadStatus::New);
});

it('creates a lead from the web form', function () {
    $manager = userWithPermissions(['sales-leads.view', 'sales-leads.create'], scope: 'all');

    $this->actingAs($manager)->post('/admin/sales-leads', [
        'name' => 'Web Lead', 'mobile' => '9111122223', 'source' => 'walk_in', 'priority' => 'high',
    ])->assertRedirect();

    expect(SalesLead::query()->where('mobile', '9111122223')->exists())->toBeTrue();
});
