<?php

namespace App\Domain\VehiclePurchases\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\VehiclePurchases\Models\SellerPayment;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maker-checker seller payments. Creation (maker) records a pending payment;
 * approval (checker, must differ from maker) marks it paid. Payments are never
 * edited or deleted — corrections go through reverse().
 */
class RecordSellerPaymentAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(VehiclePurchase $purchase, array $data, User $maker): SellerPayment
    {
        return DB::transaction(fn () => $purchase->payments()->create([
            'payment_number' => $this->sequences->next('payment'),
            'seller_id' => $purchase->seller_id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? null,
            'payment_account' => $data['payment_account'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'proof_path' => $data['proof_path'] ?? null,
            'recipient_type' => $data['recipient_type'] ?? 'seller',
            'recipient_details' => $data['recipient_details'] ?? null,
            'status' => 'pending_approval',
            'created_by' => $maker->id,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    public function approve(SellerPayment $payment, User $checker): SellerPayment
    {
        if ($payment->created_by === $checker->id && ! $checker->hasRole('Super Admin')) {
            throw new RuntimeException('The payment maker cannot approve their own payment.');
        }

        if ($payment->status !== 'pending_approval') {
            throw new RuntimeException('Only pending payments can be approved.');
        }

        $payment->update([
            'status' => 'paid',
            'approved_by' => $checker->id,
            'paid_at' => now(),
        ]);

        return $payment;
    }

    /**
     * Reverse a paid payment by posting an offsetting reversal row.
     */
    public function reverse(SellerPayment $payment, User $actor, string $remarks): SellerPayment
    {
        if ($payment->status !== 'paid') {
            throw new RuntimeException('Only paid payments can be reversed.');
        }

        if ($payment->reversal_of !== null) {
            throw new RuntimeException('A reversal entry cannot itself be reversed.');
        }

        return DB::transaction(function () use ($payment, $actor, $remarks) {
            $reversal = $payment->vehiclePurchase->payments()->create([
                'payment_number' => $this->sequences->next('payment'),
                'seller_id' => $payment->seller_id,
                'type' => $payment->type,
                'amount' => (string) (-1 * (float) $payment->amount),
                'method' => $payment->method,
                'recipient_type' => $payment->recipient_type,
                'status' => 'reversed',
                'created_by' => $actor->id,
                'reversed_by' => $actor->id,
                'reversal_of' => $payment->id,
                'remarks' => $remarks,
                'paid_at' => now(),
            ]);

            $payment->update(['status' => 'reversed', 'reversed_by' => $actor->id]);

            return $reversal;
        });
    }
}
