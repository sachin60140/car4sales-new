<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\VendorSubmissions\Models\VendorPartnerDocument;
use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-partner KYC document handling. Both the partner (self-service) and staff
 * may upload; only staff verify. The profile's kyc_status is recomputed on every
 * change and gates activation (see VendorRegistrationAction::setStatus).
 */
class VendorPartnerKycAction
{
    public function __construct(
        private readonly MediaUploadService $media,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Store (or replace) one KYC document. A re-upload resets it to pending so it
     * must be re-verified.
     */
    public function uploadDocument(VendorProfile $profile, string $type, UploadedFile $file, ?string $number, User $uploader): VendorPartnerDocument
    {
        return DB::transaction(function () use ($profile, $type, $file, $number, $uploader) {
            $stored = $this->media->store($file, "vendor-partners/{$profile->id}");

            $profile->documents()->where('type', $type)->delete();

            $document = $profile->documents()->create([
                'type' => $type,
                'file_path' => $stored['path'],
                'thumbnail_path' => $stored['thumbnail_path'] ?? null,
                'original_name' => $stored['original_name'] ?? null,
                'mime_type' => $stored['mime_type'] ?? null,
                'size_bytes' => $stored['size_bytes'] ?? null,
                'number' => $number,
                'status' => 'pending',
                'uploaded_by' => $uploader->id,
            ]);

            $this->recomputeKycStatus($profile);

            return $document;
        });
    }

    /**
     * Staff set a document's verification status (verified | rejected | pending).
     */
    public function verifyDocument(VendorProfile $profile, string $type, string $status, ?string $remarks, User $actor): VendorPartnerDocument
    {
        $document = $profile->documents()->where('type', $type)->firstOrFail();

        $document->update([
            'status' => $status,
            'remarks' => $remarks,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        $this->recomputeKycStatus($profile);

        if ($status === 'rejected') {
            $this->notifications->notify($profile->user, 'vendor.kyc-rejected', 'KYC document needs attention', [
                'level' => NotificationLevel::Warning,
                'body' => 'A KYC document was rejected — please re-upload it.',
                'action_url' => '/vendor/kyc',
            ]);
        } elseif ($profile->fresh()->kycVerified()) {
            $this->notifications->notify($profile->user, 'vendor.kyc-verified', 'KYC verified', [
                'level' => NotificationLevel::Success,
                'body' => 'Your KYC is verified — an admin can now activate your account.',
                'action_url' => '/vendor/kyc',
            ]);
        }

        return $document->fresh();
    }

    public function recomputeKycStatus(VendorProfile $profile): void
    {
        $profile->load('documents');
        $profile->update(['kyc_status' => $profile->kycStatusFromDocuments()]);
    }
}
