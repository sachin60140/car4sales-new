<?php

namespace App\Domain\VendorSubmissions\Policies;

use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Models\User;

/**
 * A vendor may only see and edit their own submissions; staff with the review
 * permission see and act on all of them.
 */
class VendorSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendor-submissions.view');
    }

    public function view(User $user, VendorSubmission $submission): bool
    {
        // Staff reviewers and document verifiers can view any submission (and its
        // media); a vendor can view only their own.
        if ($user->can('vendor-submissions.review') || $user->can('vendor-submissions.verify-documents')) {
            return true;
        }

        return $user->can('vendor-submissions.view') && $submission->vendor_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('vendor-submissions.create');
    }

    public function update(User $user, VendorSubmission $submission): bool
    {
        return $user->can('vendor-submissions.update')
            && $submission->vendor_user_id === $user->id
            && $submission->status->isEditableByVendor();
    }

    public function submit(User $user, VendorSubmission $submission): bool
    {
        return $user->can('vendor-submissions.submit')
            && $submission->vendor_user_id === $user->id
            && $submission->status->isEditableByVendor();
    }

    public function review(User $user, VendorSubmission $submission): bool
    {
        return $user->can('vendor-submissions.review');
    }

    /** Verify owner-KYC documents (a standalone right, separate from full review). */
    public function verifyDocuments(User $user, VendorSubmission $submission): bool
    {
        return $user->can('vendor-submissions.verify-documents');
    }

    /** Open the staff submission page — reviewers or document verifiers. */
    public function access(User $user, VendorSubmission $submission): bool
    {
        return $user->can('vendor-submissions.review') || $user->can('vendor-submissions.verify-documents');
    }
}
