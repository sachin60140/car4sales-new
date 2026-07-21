<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\PurchaseLeads\Actions\CreatePurchaseLeadAction;
use App\Domain\VendorSubmissions\Enums\SubmissionStatus;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The vendor-submission lifecycle: a partner drafts a vehicle offer (details +
 * condition checklist + expected price + images), submits it for review, and a
 * Purchase Manager approves it — which spins up a purchase lead — or rejects it.
 */
class VendorSubmissionAction
{
    /**
     * Vehicle/pricing fields a vendor may edit on a submission. overall_rating is
     * intentionally excluded — it is auto-calculated from the checklist ratings.
     */
    private const EDITABLE = [
        'make', 'model', 'variant', 'manufacturing_year', 'registration_number', 'registration_state',
        'fuel_type', 'transmission', 'color', 'odometer_km', 'ownership_serial',
        'expected_amount', 'overall_remark', 'branch_id',
    ];

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly CreatePurchaseLeadAction $createLead,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Create or update a draft submission and sync its condition checklist.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?VendorSubmission $submission, array $data, User $vendor): VendorSubmission
    {
        return DB::transaction(function () use ($submission, $data, $vendor) {
            $fields = array_intersect_key($data, array_flip(self::EDITABLE));

            if ($submission === null) {
                $submission = new VendorSubmission([...$fields, 'vendor_user_id' => $vendor->id, 'status' => SubmissionStatus::Draft->value]);
                $submission->submission_number = $this->sequences->next('vendor_submission');
                $submission->save();
            } else {
                if (! $submission->status->isEditableByVendor()) {
                    throw new RuntimeException('This submission can no longer be edited.');
                }
                $submission->update($fields);
            }

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $submission->items()->delete();
                foreach (array_values($data['items']) as $index => $item) {
                    $submission->items()->create([
                        'section' => $item['section'] ?? 'General',
                        'label' => $item['label'] ?? 'Item',
                        'result' => $item['result'] ?? 'na',
                        'rating' => $item['rating'] ?? null,
                        'remarks' => $item['remarks'] ?? null,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Overall rating = average of the rated checklist items (rounded).
            $ratings = $submission->items()->whereNotNull('rating')->pluck('rating');
            $submission->update([
                'overall_rating' => $ratings->isNotEmpty() ? (int) round((float) $ratings->avg()) : null,
            ]);

            return $submission->fresh(['items']);
        });
    }

    /**
     * Submit a draft for staff review. Requires an active vendor and a price.
     */
    public function submit(VendorSubmission $submission, User $vendor): VendorSubmission
    {
        if (! ($vendor->vendorProfile?->isActive() ?? false)) {
            throw new RuntimeException('Your vendor account must be activated before you can submit vehicles.');
        }

        if (! $submission->status->isEditableByVendor()) {
            throw new RuntimeException('This submission has already been submitted.');
        }

        if ((float) $submission->expected_amount <= 0) {
            throw new RuntimeException('Enter the expected amount before submitting.');
        }

        // Vehicle photos are mandatory so the reviewer can actually assess the car.
        if ($submission->galleryMedia()->count() === 0) {
            throw new RuntimeException('Upload at least one vehicle photo before submitting.');
        }

        // Any failed checklist item must be backed by a damage photo.
        if ($submission->items()->where('result', 'fail')->exists() && $submission->damageMedia()->count() === 0) {
            throw new RuntimeException('You marked one or more items as failed — upload a photo of the damage before submitting.');
        }

        $submission->update(['status' => SubmissionStatus::PendingReview->value, 'review_remarks' => null]);

        $reviewers = $this->notifications->usersWithPermission('vendor-submissions.review', $submission->branch_id);
        $this->notifications->notifyMany($reviewers, 'vendor-submission.pending', 'Vendor submission awaiting review', [
            'level' => NotificationLevel::Info,
            'body' => $submission->submission_number.' — '.$submission->title().' (₹'.number_format((float) $submission->expected_amount).')',
            'action_url' => '/admin/vendor-submissions/'.$submission->id,
            'branch_id' => $submission->branch_id,
        ]);

        return $submission->fresh();
    }

    /**
     * Approve a submission — creates a purchase lead (source = vendor) carrying
     * the vehicle details, condition summary and expected price into the pipeline.
     */
    public function approve(VendorSubmission $submission, User $reviewer, ?string $remarks = null): VendorSubmission
    {
        if ($submission->status !== SubmissionStatus::PendingReview) {
            throw new RuntimeException('Only a submission awaiting review can be approved.');
        }

        return DB::transaction(function () use ($submission, $reviewer, $remarks) {
            $vendor = $submission->vendor;
            $profile = $vendor?->vendorProfile;

            $lead = $this->createLead->execute([
                'seller_name' => $profile?->company_name ?: ($vendor?->name ?? 'Vendor'),
                'seller_type' => 'dealer',
                'mobile' => $profile?->phone ?: ($vendor?->phone ?: '0000000000'),
                'source' => 'vendor',
                'registration_number' => $submission->registration_number,
                'make' => $submission->make,
                'model' => $submission->model,
                'variant' => $submission->variant,
                'manufacturing_year' => $submission->manufacturing_year,
                'fuel_type' => $submission->fuel_type,
                'transmission' => $submission->transmission,
                'odometer_km' => $submission->odometer_km,
                'expected_price' => $submission->expected_amount,
                'branch_id' => $submission->branch_id,
                'remarks' => 'From vendor submission '.$submission->submission_number
                    .($submission->overall_remark ? ' — '.$submission->overall_remark : ''),
                'meta' => [
                    'vendor_submission_id' => $submission->id,
                    'vendor_user_id' => $vendor?->id,
                    'overall_rating' => $submission->overall_rating,
                ],
            ], $reviewer);

            $submission->update([
                'status' => SubmissionStatus::Approved->value,
                'settlement_status' => \App\Domain\VendorSubmissions\Enums\SettlementStatus::KycPending->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
                'purchase_lead_id' => $lead->id,
            ]);

            $this->notifyVendor($submission, 'approved', 'Vehicle submission approved',
                $submission->submission_number.' is approved — add the owner & bank details and upload the required documents to proceed.');

            return $submission->fresh();
        });
    }

    public function reject(VendorSubmission $submission, User $reviewer, string $remarks): VendorSubmission
    {
        if ($submission->status !== SubmissionStatus::PendingReview) {
            throw new RuntimeException('Only a submission awaiting review can be rejected.');
        }

        $submission->update([
            'status' => SubmissionStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        $this->notifyVendor($submission, 'rejected', 'Vehicle submission rejected',
            $submission->submission_number.' was rejected. '.$remarks);

        return $submission->fresh();
    }

    private function notifyVendor(VendorSubmission $submission, string $decision, string $title, string $body): void
    {
        if ($submission->vendor === null) {
            return;
        }

        $this->notifications->notify($submission->vendor, 'vendor-submission.'.$decision, $title, [
            'level' => $decision === 'approved' ? NotificationLevel::Success : NotificationLevel::Warning,
            'body' => $body,
            'action_url' => '/vendor/submissions/'.$submission->id,
        ]);
    }
}
