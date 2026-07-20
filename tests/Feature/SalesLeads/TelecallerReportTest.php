<?php

use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;

it('renders the telecaller report for a permitted manager', function () {
    $manager = userWithPermissions(['reports.view', 'telecalling.view', 'sales-leads.view'], scope: 'all');

    app(CreateSalesLeadAction::class)->execute(['name' => 'A', 'mobile' => '9000000001', 'source' => 'website'], $manager);
    app(CreateSalesLeadAction::class)->execute(['name' => 'B', 'mobile' => '9000000002', 'source' => 'walk_in'], $manager);

    $this->actingAs($manager)
        ->get('/admin/reports/telecaller')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('admin/reports/Telecaller')
            ->where('summary.total', 2)
            ->has('sources'));
});

it('forbids the report without the telecalling permission', function () {
    $user = userWithPermissions(['reports.view'], scope: 'all');

    $this->actingAs($user)->get('/admin/reports/telecaller')->assertForbidden();
});
