<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerDocument;
use App\Domain\Documents\Services\MediaUploadService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Customer KYC document handling: upload (replace-on-reupload) and staff
 * verification. The customer's kyc_status is recomputed on every change —
 * verified once all required documents are verified, partial while any are
 * present, pending when none exist.
 */
class CustomerKycAction
{
    public function __construct(private readonly MediaUploadService $media) {}

    public function uploadDocument(Customer $customer, string $type, UploadedFile $file, ?string $number, User $uploader): CustomerDocument
    {
        return DB::transaction(function () use ($customer, $type, $file, $number, $uploader) {
            $stored = $this->media->store($file, "customers/{$customer->id}");

            $customer->documents()->where('type', $type)->delete();

            $document = $customer->documents()->create([
                'type' => $type,
                'file_path' => $stored['path'],
                'number' => $number,
                'status' => 'received',
                'meta' => [
                    'original_name' => $stored['original_name'] ?? null,
                    'mime_type' => $stored['mime_type'] ?? null,
                    'uploaded_by' => $uploader->id,
                ],
            ]);

            $this->recomputeKycStatus($customer);

            return $document;
        });
    }

    public function verifyDocument(Customer $customer, string $type, string $status, ?string $rejectionReason, User $actor): CustomerDocument
    {
        $document = $customer->documents()->where('type', $type)->firstOrFail();

        $document->update([
            'status' => $status,
            'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        $this->recomputeKycStatus($customer);

        return $document->fresh();
    }

    public function recomputeKycStatus(Customer $customer): void
    {
        $customer->load('documents');
        $docs = $customer->documents;

        if ($docs->isEmpty()) {
            $status = 'pending';
        } else {
            $allRequiredVerified = collect(Customer::requiredKycTypes())
                ->every(fn (string $type) => $docs->firstWhere('type', $type)?->status === 'verified');
            $status = $allRequiredVerified ? 'verified' : 'partial';
        }

        $customer->update(['kyc_status' => $status]);
    }
}
