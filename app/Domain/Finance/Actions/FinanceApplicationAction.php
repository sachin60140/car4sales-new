<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\Disbursement;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Payments\Enums\LedgerHead;
use App\Domain\Payments\Services\LedgerService;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinanceApplicationAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly WorkflowService $workflow,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * Open a finance file for a booking. One active finance file per booking.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Booking $booking, array $data, User $actor): FinanceApplication
    {
        if (FinanceApplication::query()->where('booking_id', $booking->id)->whereNull('deleted_at')->exists()) {
            throw new RuntimeException('This booking already has a finance file.');
        }

        return DB::transaction(function () use ($booking, $data, $actor) {
            $application = FinanceApplication::query()->create([
                'application_number' => $this->sequences->next('finance_application'),
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'lender_id' => $data['lender_id'] ?? null,
                'applicant' => $data['applicant'] ?? null,
                'employer' => $data['employer'] ?? null,
                'loan_amount' => $data['loan_amount'] ?? 0,
                'down_payment' => $data['down_payment'] ?? 0,
                'tenure_months' => $data['tenure_months'] ?? null,
                'interest_rate' => $data['interest_rate'] ?? null,
                'branch_id' => $booking->branch_id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'status' => FinanceStatus::DocumentsPending->value,
                'created_by' => $actor->id,
            ]);

            $application->writeStatusHistory(null, $application->status->value, $actor, 'File created');

            return $application;
        });
    }

    /**
     * @param  array<string, mixed>  $data  {sanction_amount?, emi?, lender_id?, rejection_reason?, remarks?}
     */
    public function transition(FinanceApplication $application, FinanceStatus $to, array $data, User $actor): FinanceApplication
    {
        return DB::transaction(function () use ($application, $to, $data, $actor) {
            $updates = array_filter([
                'sanction_amount' => $data['sanction_amount'] ?? null,
                'emi' => $data['emi'] ?? null,
                'lender_id' => $data['lender_id'] ?? null,
                'lender_application_number' => $data['lender_application_number'] ?? null,
                'interest_rate' => $data['interest_rate'] ?? null,
                'tenure_months' => $data['tenure_months'] ?? null,
                'rejection_reason' => $to === FinanceStatus::Rejected ? ($data['rejection_reason'] ?? 'Rejected') : null,
                'queries' => $to === FinanceStatus::QueryRaised ? ($data['queries'] ?? null) : null,
            ], fn ($v) => $v !== null);

            if ($updates !== []) {
                $application->update($updates);
            }

            $this->workflow->transition($application, $to, $actor, $data['remarks'] ?? null);

            return $application->fresh();
        });
    }

    /**
     * Record a disbursement and post the financed amount to the customer ledger.
     */
    public function disburse(FinanceApplication $application, float $amount, ?string $utr, User $actor): Disbursement
    {
        if (! in_array($application->status, [FinanceStatus::DisbursementPending, FinanceStatus::Sanctioned, FinanceStatus::AgreementPending], true)) {
            throw new RuntimeException('The finance file is not ready for disbursement.');
        }

        return DB::transaction(function () use ($application, $amount, $utr, $actor) {
            $disbursement = Disbursement::query()->create([
                'disbursement_number' => $this->sequences->next('disbursement'),
                'finance_application_id' => $application->id,
                'amount' => $amount,
                'utr' => $utr,
                'disbursed_on' => now()->toDateString(),
                'recorded_by' => $actor->id,
            ]);

            $application->update(['disbursed_amount' => (float) $application->disbursed_amount + $amount]);

            if ($application->status !== FinanceStatus::Disbursed) {
                $this->workflow->transition($application, FinanceStatus::Disbursed, $actor, 'Disbursed', force: true);
            }

            // Credit the customer ledger with the financed amount.
            $ledger = $this->ledger->forBooking($application->booking);
            if ($ledger !== null) {
                $this->ledger->post($ledger, LedgerHead::FinanceAmount, 'credit', $amount, $actor, $disbursement, 'Finance disbursed '.$disbursement->disbursement_number);
            }

            return $disbursement;
        });
    }
}
