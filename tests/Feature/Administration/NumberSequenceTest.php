<?php

use App\Domain\Administration\Services\NumberSequenceService;

it('issues formatted sequential reference numbers', function () {
    $service = app(NumberSequenceService::class);
    $year = now()->year;

    expect($service->next('purchase_lead'))->toBe("PL-{$year}-000001")
        ->and($service->next('purchase_lead'))->toBe("PL-{$year}-000002")
        ->and($service->next('purchase_lead'))->toBe("PL-{$year}-000003");
});

it('keeps independent counters per sequence key', function () {
    $service = app(NumberSequenceService::class);
    $year = now()->year;

    $service->next('purchase_lead');

    expect($service->next('booking'))->toBe("BKG-{$year}-000001")
        ->and($service->next('rto_case'))->toBe("RTO-{$year}-000001");
});

it('keeps independent counters per year', function () {
    $service = app(NumberSequenceService::class);

    expect($service->next('stock', year: 2025))->toBe('STK-2025-000001')
        ->and($service->next('stock', year: 2026))->toBe('STK-2026-000001')
        ->and($service->next('stock', year: 2025))->toBe('STK-2025-000002');
});

it('rejects unknown sequence keys', function () {
    app(NumberSequenceService::class)->next('not_a_real_key');
})->throws(InvalidArgumentException::class);

it('applies the configured employee prefix and padding', function () {
    $service = app(NumberSequenceService::class);
    $year = now()->year;

    expect($service->next('employee'))->toBe("EMP-{$year}-0001");
});
