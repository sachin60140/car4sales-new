<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Customers\Models\Customer;

/**
 * Finds an existing customer by mobile, or creates one. Keeps a single customer
 * record per mobile number so their lead history stays unified.
 */
class ResolveCustomerAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Customer
    {
        $customer = Customer::query()->where('mobile', $data['mobile'])->first();

        if ($customer !== null) {
            // Backfill any newly-supplied contact details.
            $customer->fill(array_filter([
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'branch_id' => $customer->branch_id ?? ($data['branch_id'] ?? null),
            ]));
            if ($customer->isDirty()) {
                $customer->save();
            }

            return $customer;
        }

        return Customer::query()->create([
            'customer_code' => $this->sequences->next('customer'),
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'kyc_status' => 'pending',
        ]);
    }
}
