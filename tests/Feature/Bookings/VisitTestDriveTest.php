<?php

use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\TestDrives\Actions\TestDriveAction;
use App\Domain\Visits\Actions\ScheduleVisitAction;
use App\Domain\Visits\Enums\VisitStatus;

it('schedules a visit and advances the lead', function () {
    $admin = superAdmin();
    $lead = app(CreateSalesLeadAction::class)->execute(['name' => 'V', 'mobile' => '9000000701', 'source' => 'website'], $admin);
    $lead->update(['status' => SalesLeadStatus::Interested->value]);

    $visit = app(ScheduleVisitAction::class)->schedule($lead->fresh(), ['scheduled_at' => now()->addDay()->toDateTimeString()], $admin);

    expect($visit->visit_number)->toStartWith('VIS-')
        ->and($visit->status)->toBe(VisitStatus::Scheduled)
        ->and($lead->fresh()->status)->toBe(SalesLeadStatus::VisitScheduled);

    app(ScheduleVisitAction::class)->complete($visit->fresh(), ['outcome' => 'Interested in Swift'], $admin);
    expect($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($lead->fresh()->status)->toBe(SalesLeadStatus::VisitCompleted);
});

it('schedules a test drive and advances the lead', function () {
    $admin = superAdmin();
    $lead = app(CreateSalesLeadAction::class)->execute(['name' => 'T', 'mobile' => '9000000702', 'source' => 'website'], $admin);
    $lead->update(['status' => SalesLeadStatus::Interested->value]);
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-TD1', 'make' => 'Kia', 'model' => 'Seltos',
        'status' => VehicleStatus::ReadyForSale->value,
    ]);

    $td = app(TestDriveAction::class)->schedule($lead->fresh(), $vehicle, ['scheduled_at' => now()->addDay()->toDateTimeString()], $admin);

    expect($td->td_number)->toStartWith('TD-')
        ->and($lead->fresh()->status)->toBe(SalesLeadStatus::TestDrive);

    app(TestDriveAction::class)->complete($td->fresh(), ['feedback' => 'Liked it', 'end_odometer' => 15020], $admin);
    expect($td->fresh()->status)->toBe('completed');
});
