<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Customers\Models\Customer;

/**
 * Creates or updates a customer from admin input. A new customer gets the next
 * customer code and a pending KYC status; mobile-number uniqueness is enforced
 * at the request layer so the single-record-per-mobile rule still holds.
 */
class SaveCustomerAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?Customer $customer, array $data): Customer
    {
        if ($customer === null) {
            $customer = new Customer([
                'customer_code' => $this->sequences->next('customer'),
                'kyc_status' => 'pending',
            ]);
        }

        $customer->fill([
            'name' => $data['name'],
            'father_name' => $data['father_name'] ?? null,
            'mobile' => $data['mobile'],
            'alt_mobile' => $data['alt_mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pin_code' => $data['pin_code'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'dob' => $data['dob'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
        ]);

        // KYC identity numbers are only written when supplied (a user without the
        // view-kyc permission never sends them — see CustomerController).
        if (array_key_exists('aadhaar_number', $data)) {
            $customer->aadhaar_number = $data['aadhaar_number'];
        }
        if (array_key_exists('pan_number', $data)) {
            $customer->pan_number = $data['pan_number'] !== null ? strtoupper($data['pan_number']) : null;
        }

        $customer->save();

        return $customer;
    }
}
