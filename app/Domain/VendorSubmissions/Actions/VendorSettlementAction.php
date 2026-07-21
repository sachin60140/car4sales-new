<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inventory\Actions\CreateStockFromVendorSubmissionAction;
use App\Domain\Inventory\Models\Vehicle;
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
        private readonly CreateStockFromVendorSubmissionAction $createStock,
    ) {}

    /**
     * Vendor submits the vehicle owner's details, the owner's payout bank account,
     * chassis number, hypothecation status, and the KYC documents. RC & Aadhaar are
     * two-sided (`*_front`/`*_back`); NOC & Form 35 are required only under hypothecation.
     *
     * @param  array<string, mixed>  $owner   owner_* fields + chassis_number + has_hypothecation
     * @param  array<string, mixed>  $bank    the owner's account: bank_account_name, bank_account_number, bank_ifsc, bank_name
     * @param  array<string, UploadedFile>  $files   files keyed by media type (rc_front, aadhaar_back, pan, …)
     * @param  array<int, UploadedFile>  $extraDocuments  optional additional docs
     */
    public function submitOwnerKyc(VendorSubmission $submission, array $owner, array $bank, array $files, User $vendor, array $extraDocuments = []): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycPending) {
            throw new RuntimeException('Owner details can only be submitted after the vehicle is approved.');
        }

        return DB::transaction(function () use ($submission, $owner, $bank, $files, $vendor, $extraDocuments) {
            foreach ($files as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeDoc($submission, $type, $file, $vendor, replace: true);
                }
            }
            foreach ($extraDocuments as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeDoc($submission, 'other_doc', $file, $vendor, replace: false);
                }
            }

            $hasHypothecation = (bool) ($owner['has_hypothecation'] ?? false);

            // Seed a pending verification for every catalog document that now has media
            // (based on what is actually stored, so a partial re-submit keeps prior docs).
            $submission->load('media');
            $verifications = [];
            foreach (VendorSubmission::documentCatalog($hasHypothecation) as $key => $def) {
                $types = VendorSubmission::docMediaTypes($key, $def['sides']);
                if ($submission->media->whereIn('type', $types)->isNotEmpty()) {
                    $verifications[$key] = ['status' => 'pending', 'number' => null, 'valid_till' => null, 'remarks' => null];
                }
            }

            $submission->update([
                'settlement_status' => SettlementStatus::KycSubmitted->value,
                'owner_name' => $owner['owner_name'],
                'owner_phone' => $owner['owner_phone'] ?? null,
                'owner_email' => $owner['owner_email'] ?? null,
                'owner_address' => $owner['owner_address'] ?? null,
                'owner_pan' => $owner['owner_pan'] ?? null,
                'chassis_number' => $owner['chassis_number'] ?? null,
                'has_hypothecation' => $hasHypothecation,
                'bank_account_name' => $bank['bank_account_name'],
                'bank_account_number' => $bank['bank_account_number'],
                'bank_ifsc' => $bank['bank_ifsc'],
                'bank_name' => $bank['bank_name'] ?? null,
                'document_verifications' => $verifications,
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

    /** Staff set the verification status of one document (pending/verified/rejected/…). */
    public function verifyDocument(VendorSubmission $submission, string $type, array $data, User $actor): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycSubmitted) {
            throw new RuntimeException('Documents can only be verified while they are under review.');
        }

        $verifications = $submission->document_verifications ?? [];
        $verifications[$type] = [
            'status' => $data['status'],
            'number' => $data['number'] ?? null,
            'valid_till' => $data['valid_till'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            // Audit — which employee set this status and when.
            'verified_by' => $actor->id,
            'verified_by_name' => $actor->name,
            'verified_at' => now()->toDateTimeString(),
        ];

        $submission->update(['document_verifications' => $verifications]);

        return $submission->fresh();
    }

    /**
     * Staff issue the agreement once every required document is verified — the
     * (dynamic) agreement becomes available and the vendor can request payment.
     */
    public function approveOwnerKyc(VendorSubmission $submission, User $actor, ?string $remarks = null): VendorSubmission
    {
        if ($submission->settlement_status !== SettlementStatus::KycSubmitted) {
            throw new RuntimeException('Only submitted owner documents can be approved.');
        }

        $catalog = VendorSubmission::documentCatalog($submission->has_hypothecation);
        $verifs = $submission->document_verifications ?? [];
        $missing = array_filter(
            VendorSubmission::requiredDocKeys($submission->has_hypothecation),
            fn ($key) => ($verifs[$key]['status'] ?? null) !== 'verified',
        );

        if ($missing !== []) {
            $labels = array_map(fn ($key) => $catalog[$key]['label'] ?? $key, $missing);
            throw new RuntimeException('Verify all required documents first — pending: '.implode(', ', $labels).'.');
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

    /**
     * Staff confirm physical possession of the vehicle (document/key checklist +
     * odometer + fuel) and — atomically — create the stock (inventory) entry.
     *
     * @param  array<string, mixed>  $checklist
     * @return array{submission: VendorSubmission, vehicle: Vehicle}
     */
    public function confirmPossession(VendorSubmission $submission, array $checklist, User $actor): array
    {
        if ($submission->settlement_status !== SettlementStatus::Paid) {
            throw new RuntimeException('Possession can only be confirmed after the payment is recorded.');
        }

        if (empty($checklist['vehicle_received'])) {
            throw new RuntimeException('The vehicle must be physically received to confirm possession.');
        }

        return DB::transaction(function () use ($submission, $checklist, $actor) {
            $odometer = isset($checklist['odometer_km']) ? (int) $checklist['odometer_km'] : null;
            $vehicle = $this->createStock->execute($submission, $actor, $odometer);

            $submission->update([
                'settlement_status' => SettlementStatus::Stocked->value,
                'vehicle_id' => $vehicle->id,
                'possession' => $checklist,
                'possession_confirmed_at' => now(),
                'possessed_by' => $actor->id,
            ]);

            $this->notifyVendor($submission, 'vendor-settlement.stocked', 'Vehicle stocked', NotificationLevel::Success,
                $submission->submission_number.': the vehicle has been received and added to our inventory as '.$vehicle->stock_number.'.');

            return ['submission' => $submission->fresh(), 'vehicle' => $vehicle];
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
