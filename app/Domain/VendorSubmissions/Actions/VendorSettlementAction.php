<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\VendorSubmissions\Enums\SettlementStatus;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Post-approval settlement: the vendor requests payment (bank details + cancelled
 * cheque), then staff record the payment (details + screenshot). Drives the
 * submission's settlement_status: agreement_ready → payment_requested → paid.
 */
class VendorSettlementAction
{
    public function __construct(
        private readonly MediaUploadService $media,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Vendor submits payout bank details + a cancelled cheque.
     *
     * @param  array<string, mixed>  $data
     */
    public function requestPayment(VendorSubmission $submission, array $data, UploadedFile $cheque, User $vendor): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::AgreementReady) {
            throw new RuntimeException('Payment can only be requested once the submission is approved.');
        }

        return DB::transaction(function () use ($submission, $data, $cheque, $vendor) {
            $stored = $this->media->store($cheque, "vendor-submissions/{$submission->id}");

            // Replace any previous cheque.
            $submission->chequeMedia()->delete();
            $submission->media()->create([
                'type' => 'cancelled_cheque',
                'file_path' => $stored['path'],
                'thumbnail_path' => $stored['thumbnail_path'] ?? null,
                'original_name' => $stored['original_name'] ?? null,
                'mime_type' => $stored['mime_type'] ?? null,
                'size_bytes' => $stored['size_bytes'] ?? null,
                'uploaded_by' => $vendor->id,
            ]);

            $submission->update([
                'settlement_status' => SettlementStatus::PaymentRequested->value,
                'bank_account_name' => $data['bank_account_name'],
                'bank_account_number' => $data['bank_account_number'],
                'bank_ifsc' => $data['bank_ifsc'],
                'bank_name' => $data['bank_name'] ?? null,
                'payment_requested_at' => now(),
            ]);

            $reviewers = $this->notifications->usersWithPermission('vendor-submissions.review', $submission->branch_id);
            $this->notifications->notifyMany($reviewers, 'vendor-settlement.requested', 'Vendor payment requested', [
                'level' => NotificationLevel::Warning,
                'body' => $submission->submission_number.' — '.$submission->title().': the vendor has requested payment.',
                'action_url' => '/admin/vendor-submissions/'.$submission->id,
                'branch_id' => $submission->branch_id,
            ]);

            return $submission->fresh();
        });
    }

    /**
     * Staff record the payment made to the vendor + a proof screenshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(VendorSubmission $submission, array $data, ?UploadedFile $proof, User $actor): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::PaymentRequested) {
            throw new RuntimeException('Only a requested payment can be recorded.');
        }

        return DB::transaction(function () use ($submission, $data, $proof, $actor) {
            if ($proof !== null) {
                $stored = $this->media->store($proof, "vendor-submissions/{$submission->id}");
                $submission->paymentProofMedia()->delete();
                $submission->media()->create([
                    'type' => 'payment_proof',
                    'file_path' => $stored['path'],
                    'thumbnail_path' => $stored['thumbnail_path'] ?? null,
                    'original_name' => $stored['original_name'] ?? null,
                    'mime_type' => $stored['mime_type'] ?? null,
                    'size_bytes' => $stored['size_bytes'] ?? null,
                    'uploaded_by' => $actor->id,
                ]);
            }

            $submission->update([
                'settlement_status' => SettlementStatus::Paid->value,
                'payment_amount' => $data['payment_amount'],
                'payment_mode' => $data['payment_mode'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'paid_by' => $actor->id,
                'paid_at' => now(),
            ]);

            $this->notifications->notify($submission->vendor, 'vendor-settlement.paid', 'Payment released', [
                'level' => NotificationLevel::Success,
                'body' => 'Payment of ₹'.number_format((float) $submission->payment_amount).' for '.$submission->submission_number.' has been recorded.',
                'action_url' => '/vendor/submissions/'.$submission->id,
            ]);

            return $submission->fresh();
        });
    }
}
