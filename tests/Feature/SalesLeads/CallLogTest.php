<?php

use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Actions\LogCallAction;
use App\Domain\SalesLeads\Enums\CallOutcome;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadLostReason;
use App\Domain\SalesLeads\Models\SalesLead;
use Illuminate\Validation\ValidationException;

function freshLead(): SalesLead
{
    return app(CreateSalesLeadAction::class)->execute(
        ['name' => 'Caller Test', 'mobile' => '90000'.fake()->unique()->numerify('#####'), 'source' => 'manual'],
        superAdmin(),
    );
}

it('advances to contacted and stamps first response on a connected call', function () {
    $actor = superAdmin();
    $lead = freshLead();

    app(LogCallAction::class)->execute($lead, CallOutcome::Connected, ['remarks' => 'spoke'], $actor);

    $lead->refresh();
    expect($lead->status)->toBe(SalesLeadStatus::Contacted)
        ->and($lead->first_response_at)->not->toBeNull();
    $this->assertDatabaseHas('lead_followups', ['sales_lead_id' => $lead->id, 'call_outcome' => 'connected']);
});

it('requires a next follow-up for an interested outcome', function () {
    app(LogCallAction::class)->execute(freshLead(), CallOutcome::Interested, ['remarks' => 'keen'], superAdmin());
})->throws(ValidationException::class);

it('marks interested with a follow-up date', function () {
    $lead = freshLead();
    app(LogCallAction::class)->execute($lead, CallOutcome::Interested, ['next_follow_up_at' => now()->addDay()->toDateTimeString()], superAdmin());

    expect($lead->fresh()->status)->toBe(SalesLeadStatus::Interested)
        ->and($lead->fresh()->next_follow_up_at)->not->toBeNull();
});

it('requires a lost reason for a not-interested outcome', function () {
    app(LogCallAction::class)->execute(freshLead(), CallOutcome::NotInterested, [], superAdmin());
})->throws(ValidationException::class);

it('marks the lead lost with a reason', function () {
    $reason = LeadLostReason::query()->create(['label' => 'Too costly', 'category' => 'price']);
    $lead = freshLead();

    app(LogCallAction::class)->execute($lead, CallOutcome::NotInterested, ['lost_reason_id' => $reason->id], superAdmin());

    expect($lead->fresh()->status)->toBe(SalesLeadStatus::Lost)
        ->and($lead->fresh()->lost_reason_id)->toBe($reason->id);
});

it('marks a wrong number', function () {
    $lead = freshLead();
    app(LogCallAction::class)->execute($lead, CallOutcome::WrongNumber, ['lost_reason_id' => LeadLostReason::query()->create(['label' => 'x'])->id], superAdmin());

    expect($lead->fresh()->status)->toBe(SalesLeadStatus::WrongNumber);
});

it('logs a call through the mobile API and returns the new status', function () {
    $user = userWithPermissions(['access-mobile', 'sales-leads.view', 'sales-leads.update'], scope: 'all');
    $lead = freshLead();
    $lead->update(['telecaller_id' => $user->id]);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel',
    ])->json('data.token');

    $this->postJson("/api/v1/sales-leads/{$lead->id}/call-log", [
        'outcome' => 'connected', 'remarks' => 'via app',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.status', 'contacted');
});
