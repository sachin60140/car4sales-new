<?php

namespace App\Domain\VehicleVerification\Services;

use App\Domain\PurchaseLeads\Models\PurchaseLead;

class VerificationChecklist
{
    /**
     * Standard vehicle-document verification items (spec §11).
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'rc_original', 'rc_copy', 'insurance', 'puc', 'tax', 'fitness', 'permit',
        'hypothecation', 'bank_noc', 'form_35', 'challan', 'blacklist',
        'service_history', 'keys', 'purchase_invoice',
    ];

    /**
     * Create pending verification rows for a lead (idempotent).
     */
    public function seed(PurchaseLead $lead): void
    {
        $existing = $lead->verifications()->pluck('type')->all();

        foreach (self::TYPES as $type) {
            if (! in_array($type, $existing, true)) {
                $lead->verifications()->create(['type' => $type, 'status' => 'pending']);
            }
        }
    }
}
