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
 * Post-approval settlement. After a submission is approved the flow is:
 *   kyc_pending  → vendor submits owner + bank details and KYC documents
 *   kyc_submitted → staff verify the documents (approve → agreement_ready, or send back)
 *   agreement_ready → vendor requests payment (bank already on file)
 *   payment_requested → staff record the payment (details + screenshot) → paid
 *
 * Owner details and documents live on the submission only — they are never copied
 * into the purchase lead.
 */
class VendorSettlementAction
{
    public function __construct(
        private readonly MediaUploadService $media,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Vendor submits the vehicle owner's details, payout bank details, and the
     * required KYC documents (RC, PAN, Aadhaar, NOC, key/owner photos, cheque, …).
     *
     * @param  array<string, mixed>  $owner   owner_name, owner_phone, owner_email, owner_address, owner_pan
     * @param  array<string, mixed>  $bank    bank_account_name, bank_account_number, bank_ifsc, bank_name
     * @param  array<string, UploadedFile>  $documents  single files keyed by type
     * @param  array<int, UploadedFile>  $extraDocuments  optional additional docs
     */
    public function submitOwnerKyc(VendorSubmission $submission, array $owner, array $bank, array $documents, User $vendor, array $extraDocuments = []): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycPending) {
            throw new RuntimeException('Owner details can only be submitted after the vehicle is approved.');
        }

        return DB::transaction(function () use ($submission, $owner, $bank, $documents, $vendor, $extraDocuments) {
            foreach ($documents as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeDoc($submission, $type, $file, $vendor, replace: true);
                }
            }
            foreach ($extraDocuments as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeDoc($submission, 'other_doc', $file, $vendor, replace: false);
                }
            }

            $submission->update([
                'settlement_status' => SettlementStatus::KycSubmitted->value,
                'owner_name' => $owner['owner_name'],
                'owner_phone' => $owner['owner_phone'] ?? null,
                'owner_email' => $owner['owner_email'] ?? null,
                'owner_address' => $owner['owner_address'] ?? null,
                'owner_pan' => $owner['owner_pan'] ?? null,
                'bank_account_name' => $bank['bank_account_name'],
                'bank_account_number' => $bank['bank_account_number'],
                'bank_ifsc' => $bank['bank_ifsc'],
                'bank_name' => $bank['bank_name'] ?? null,
                'kyc_submitted_at' => now(),
                'kyc_remarks' => null,
            ]);

            $reviewers = $this->notifications->usersWithPermission('vendor-submissions.review', $submission->branch_id);
            $this->notifications->notifyMany($reviewers, 'vendor-settlement.kyc-submitted', 'Owner documents submitted', [
                'level' => NotificationLevel::Info,
                'body' => $submission->submission_number.' — '.$submission->title().': owner details & documents are ready for verification.',
                'action_url' => '/admin/vendor-submissions/'.$submission->id,
                'branch_id' => $submission->branch_id,
            ]);

            return $submission->fresh();
        });
    }

    /**
     * Staff verify the owner documents — the agreement (with the real owner name +
     * registration number) becomes available and payment can be requested.
     */
    public function approveOwnerKyc(VendorSubmission $submission, User $actor, ?string $remarks = null): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycSubmitted) {
            throw new RuntimeException('Only submitted owner documents can be approved.');
        }

        $submission->update([
            'settlement_status' => SettlementStatus::AgreementReady->value,
            'kyc_approved_at' => now(),
            'kyc_approved_by' => $actor->id,
            'kyc_remarks' => $remarks,
        ]);

        $this->notifyVendor($submission, 'vendor-settlement.kyc-approved', 'Documents verified', NotificationLevel::Success,
            $submission->submission_number.': your documents are verified — download the agreement and request payment.');

        return $submission->fresh();
    }

    /** Staff send the owner documents back to the vendor for correction. */
    public function rejectOwnerKyc(VendorSubmission $submission, User $actor, string $remarks): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycSubmitted) {
            throw new RuntimeException('Only submitted owner documents can be sent back.');
        }

        $submission->update([
            'settlement_status' => SettlementStatus::KycPending->value,
            'kyc_remarks' => $remarks,
        ]);

        $this->notifyVendor($submission, 'vendor-settlement.kyc-rejected', 'Documents need attention', NotificationLevel::Warning,
            $submission->submission_number.': please correct and resubmit your documents. '.$remarks);

        return $submission->fresh();
    }

    /**
     * Vendor requests payment once the agreement is ready. Bank details were captured
     * during owner-KYC, so this simply flags the submission for the payment team.
     */
    public function requestPayment(VendorSubmission $submission, User $vendor): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::AgreementReady) {
            throw new RuntimeException('Payment can only be requested once your documents are verified.');
        }

        $submission->update([
            'settlement_status' => SettlementStatus::PaymentRequested->value,
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
                $this->storeDoc($submission, 'payment_proof', $proof, $actor, replace: true);
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

    /** Store one document, optionally replacing any previous file of the same type. */
    private function storeDoc(VendorSubmission $submission, string $type, UploadedFile $file, User $uploader, bool $replace): void
    {
        $stored = $this->media->store($file, "vendor-submissions/{$submission->id}");

        if ($replace) {
            $submission->media()->where('type', $type)->delete();
        }

        $submission->media()->create([
            'type' => $type,
            'file_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'] ?? null,
            'original_name' => $stored['original_name'] ?? null,
            'mime_type' => $stored['mime_type'] ?? null,
            'size_bytes' => $stored['size_bytes'] ?? null,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function notifyVendor(VendorSubmission $submission, string $event, string $title, NotificationLevel $level, string $body): void
    {
        if ($submission->vendor === null) {
            return;
        }

        $this->notifications->notify($submission->vendor, $event, $title, [
            'level' => $level,
            'body' => $body,
            'action_url' => '/vendor/submissions/'.$submission->id,
        ]);
    }
}
