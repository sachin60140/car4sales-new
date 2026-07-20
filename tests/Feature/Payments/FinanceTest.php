<?php

use App\Domain\Finance\Actions\FinanceApplicationAction;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Payments\Services\InvoiceService;
use App\Domain\Payments\Services\LedgerService;

it('creates one finance file per booking', function () {
    [$booking, $admin] = ledgerBooking(['payment_mode' => 'finance']);

    $app = app(FinanceApplicationAction::class)->create($booking, ['loan_amount' => 600000, 'down_payment' => 100000], $admin);

    expect($app->application_number)->toStartWith('FIN-')
        ->and($app->status)->toBe(FinanceStatus::DocumentsPending);

    expect(fn () => app(FinanceApplicationAction::class)->create($booking->fresh(), ['loan_amount' => 1], $admin))
        ->toThrow(RuntimeException::class, 'already has a finance file');
});

it('runs the finance workflow and disburses to the ledger', function () {
    [$booking, $admin] = ledgerBooking(['payment_mode' => 'finance']);
    $action = app(FinanceApplicationAction::class);
    $app = $action->create($booking, ['loan_amount' => 600000], $admin);

    foreach (['file_ready', 'submitted', 'logged_in', 'credit_pending', 'sanctioned', 'agreement_pending', 'disbursement_pending'] as $s) {
        $action->transition($app->fresh(), FinanceStatus::from($s), ['sanction_amount' => 600000, 'emi' => 13500], $admin);
    }
    expect($app->fresh()->status)->toBe(FinanceStatus::DisbursementPending);

    $before = app(LedgerService::class)->forBooking($booking)->outstanding();
    $action->disburse($app->fresh(), 600000, 'UTR777', $admin);

    expect($app->fresh()->status)->toBe(FinanceStatus::Disbursed)
        ->and((float) $app->fresh()->disbursed_amount)->toBe(600000.0)
        // Disbursement credits the ledger, reducing outstanding by 600000.
        ->and(app(LedgerService::class)->forBooking($booking)->outstanding())->toBe(round($before - 600000, 2));
});

it('blocks an illegal finance transition', function () {
    [$booking, $admin] = ledgerBooking(['payment_mode' => 'finance']);
    $app = app(FinanceApplicationAction::class)->create($booking, ['loan_amount' => 600000], $admin);

    // documents_pending cannot jump straight to disbursed.
    expect(fn () => app(FinanceApplicationAction::class)->transition($app, FinanceStatus::Disbursed, [], $admin))
        ->toThrow(App\Support\Workflow\InvalidTransitionException::class);
});

it('generates a single invoice with correct totals', function () {
    [$booking, $admin] = ledgerBooking();

    $invoice = app(InvoiceService::class)->generate($booking, $admin);

    expect($invoice->invoice_number)->toStartWith('INV-')
        ->and((float) $invoice->total)->toBe(730000.0)
        ->and($invoice->generated_document_id)->not->toBeNull();

    // Idempotent — one invoice per booking.
    $again = app(InvoiceService::class)->generate($booking->fresh(), $admin);
    expect($again->id)->toBe($invoice->id);
});

it('creates a finance file from the web endpoint', function () {
    $user = userWithPermissions(['finance.view', 'finance.create'], scope: 'all');
    [$booking] = ledgerBooking(['payment_mode' => 'finance']);

    $this->actingAs($user)->post('/admin/finance', [
        'booking_id' => $booking->id, 'loan_amount' => 500000, 'down_payment' => 50000,
    ])->assertRedirect();

    expect(FinanceApplication::query()->where('booking_id', $booking->id)->exists())->toBeTrue();
});

it('refuses to mark a finance file Disbursed through the generic status control', function () {
    [$booking, $admin] = ledgerBooking(['payment_mode' => 'finance']);
    $action = app(FinanceApplicationAction::class);
    $app = $action->create($booking, ['loan_amount' => 600000], $admin);

    foreach (['file_ready', 'submitted', 'logged_in', 'credit_pending', 'sanctioned', 'agreement_pending', 'disbursement_pending'] as $s) {
        $action->transition($app->fresh(), FinanceStatus::from($s), [], $admin);
    }
    expect($app->fresh()->status)->toBe(FinanceStatus::DisbursementPending);

    $this->actingAs($admin)
        ->from("/admin/finance/{$app->id}")
        ->post("/admin/finance/{$app->id}/transition", ['status' => 'disbursed'])
        ->assertRedirect("/admin/finance/{$app->id}")
        ->assertSessionHas('error');

    // Status unchanged, no disbursement record, no ledger credit.
    $app->refresh();
    expect($app->status)->toBe(FinanceStatus::DisbursementPending)
        ->and($app->disbursements()->count())->toBe(0);

    $ledger = app(LedgerService::class)->forBooking($booking);
    expect($ledger?->entries()->where('head', 'finance_amount')->count() ?? 0)->toBe(0);
});

it('omits Disbursed from the finance transition dropdown', function () {
    [$booking, $admin] = ledgerBooking(['payment_mode' => 'finance']);
    $action = app(FinanceApplicationAction::class);
    $app = $action->create($booking, ['loan_amount' => 600000], $admin);
    foreach (['file_ready', 'submitted', 'logged_in', 'credit_pending', 'sanctioned', 'agreement_pending', 'disbursement_pending'] as $s) {
        $action->transition($app->fresh(), FinanceStatus::from($s), [], $admin);
    }

    $this->actingAs($admin)
        ->get("/admin/finance/{$app->id}")
        ->assertInertia(fn ($p) => $p->where('allowedTransitions', fn ($t) => collect($t)->pluck('value')->doesntContain('disbursed')));
});
