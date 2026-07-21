<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\VendorSubmissions\Models\VendorSubmission;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the pre-filled vendor purchase agreement PDF — terms & conditions plus
 * the RTO transfer forms (Form 29 / Form 30), filled from the vehicle, the seller
 * (vendor) and the buyer (dealership).
 */
class GenerateVendorAgreementAction
{
    public function pdf(VendorSubmission $submission): \Barryvdh\DomPDF\PDF
    {
        $submission->loadMissing(['vendor.vendorProfile', 'branch']);

        return Pdf::loadView('documents.vendor_agreement', [
            'submission' => $submission,
            'vendor' => $submission->vendor,
            'profile' => $submission->vendor?->vendorProfile,
            'generatedAt' => now(),
            'buyerName' => config('car4sales.public.company_name', config('app.name')),
        ])->setPaper('a4');
    }
}
