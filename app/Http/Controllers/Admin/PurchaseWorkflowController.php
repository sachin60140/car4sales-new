<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\PurchaseApprovals\Actions\CompletePurchaseFromApprovalAction;
use App\Domain\PurchaseApprovals\Actions\RequestPurchaseApprovalAction;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\Valuations\Actions\SaveValuationAction;
use App\Domain\VehiclePurchases\Actions\ConfirmPossessionAction;
use App\Domain\VehiclePurchases\Actions\GeneratePurchaseAgreementAction;
use App\Domain\VehiclePurchases\Actions\RecordSellerPaymentAction;
use App\Domain\VehiclePurchases\Models\SellerPayment;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseWorkflowController extends Controller
{
    public function followup(Request $request, PurchaseLead $purchaseLead): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);

        $data = $request->validate([
            'contact_mode' => ['required', 'string', 'in:call,whatsapp,visit,other'],
            'outcome' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $purchaseLead->followups()->create([...$data, 'user_id' => $request->user()->id]);

        if (! empty($data['next_follow_up_at'])) {
            $purchaseLead->update(['next_follow_up_at' => $data['next_follow_up_at']]);
        }

        return back()->with('success', 'Follow-up added.');
    }

    public function uploadDocument(Request $request, PurchaseLead $purchaseLead, MediaUploadService $media): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $stored = $media->store($data['file'], "sellers/lead-{$purchaseLead->id}");

        $purchaseLead->documents()->create([
            'seller_id' => $purchaseLead->seller_id,
            'type' => $data['type'],
            'file_path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'status' => 'received',
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function updateVerification(Request $request, PurchaseLead $purchaseLead): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);
        abort_unless($request->user()->can('vehicle-verifications.update'), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'status' => ['required', 'string', 'in:pending,received,verified,rejected,expired,not_applicable'],
            'number' => ['nullable', 'string', 'max:255'],
            'valid_till' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $purchaseLead->verifications()->updateOrCreate(
            ['type' => $data['type']],
            [
                'status' => $data['status'],
                'number' => $data['number'] ?? null,
                'valid_till' => $data['valid_till'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'verified_by' => in_array($data['status'], ['verified', 'rejected'], true) ? $request->user()->id : null,
                'verified_at' => in_array($data['status'], ['verified', 'rejected'], true) ? now() : null,
            ],
        );

        return back()->with('success', 'Verification updated.');
    }

    public function saveValuation(Request $request, PurchaseLead $purchaseLead, SaveValuationAction $action): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);
        abort_unless($request->user()->can('valuations.create'), 403);

        $data = $this->valuationRules($request);
        $action->execute($purchaseLead, $data, $request->user());

        return back()->with('success', 'Valuation saved.');
    }

    public function requestApproval(Request $request, PurchaseLead $purchaseLead, RequestPurchaseApprovalAction $action): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);
        abort_unless($request->user()->can('purchase-approvals.create'), 403);

        $data = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($purchaseLead, (float) $data['requested_amount'], $request->user(), $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase approval requested.');
    }

    public function decideApproval(Request $request, ApprovalRequest $approvalRequest, ApprovalEngine $engine, CompletePurchaseFromApprovalAction $complete): RedirectResponse
    {
        $this->authorize('decide', $approvalRequest);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['decision'] === 'approve') {
            $result = $engine->approve($approvalRequest, $request->user(), isset($data['approved_amount']) ? (float) $data['approved_amount'] : null, $data['remarks'] ?? null);

            // On final approval of a purchase approval, spin up the purchase record.
            if ($result->status->value === 'approved' && $result->module === 'purchase-approval') {
                $complete->execute($result, $request->user());
            }

            return back()->with('success', 'Approval recorded.');
        }

        $engine->reject($approvalRequest, $request->user(), $data['remarks'] ?? 'Rejected');

        return back()->with('success', 'Approval rejected.');
    }

    public function generateAgreement(Request $request, VehiclePurchase $purchase, GeneratePurchaseAgreementAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('vehicle-purchases.create'), 403);

        $action->execute($purchase, $request->user());

        return back()->with('success', 'Purchase agreement generated.');
    }

    public function recordPayment(Request $request, VehiclePurchase $purchase, RecordSellerPaymentAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('seller-payments.create'), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'recipient_type' => ['nullable', 'string', 'in:seller,bank,registered_owner,broker'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->create($purchase, $data, $request->user());

        return back()->with('success', 'Payment recorded (pending approval).');
    }

    public function approvePayment(Request $request, SellerPayment $payment, RecordSellerPaymentAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('seller-payments.approve'), 403);

        $action->approve($payment, $request->user());

        return back()->with('success', 'Payment approved.');
    }

    public function reversePayment(Request $request, SellerPayment $payment, RecordSellerPaymentAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('seller-payments.reverse-payment'), 403);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        $action->reverse($payment, $request->user(), $data['remarks']);

        return back()->with('success', 'Payment reversed.');
    }

    public function confirmPossession(Request $request, VehiclePurchase $purchase, ConfirmPossessionAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('possessions.create'), 403);

        $checklist = $request->validate([
            'vehicle_received' => ['required', 'boolean'],
            'original_rc_received' => ['boolean'],
            'insurance_received' => ['boolean'],
            'puc_received' => ['boolean'],
            'noc_received' => ['boolean'],
            'form_35_received' => ['boolean'],
            'main_key' => ['boolean'],
            'spare_key' => ['boolean'],
            'service_book' => ['boolean'],
            'tool_kit' => ['boolean'],
            'spare_wheel' => ['boolean'],
            'accessories' => ['boolean'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $action->execute($purchase, $checklist, $request->user());

        return back()->with('success', 'Possession confirmed. Stock '.$result['vehicle']->stock_number.' created.');
    }

    private function valuationRules(Request $request): array
    {
        return $request->validate([
            'market_price' => ['nullable', 'numeric', 'min:0'],
            'expected_retail_price' => ['required', 'numeric', 'min:0'],
            'seller_expected_price' => ['nullable', 'numeric', 'min:0'],
            'repair_estimate' => ['nullable', 'numeric', 'min:0'],
            'rto_expense' => ['nullable', 'numeric', 'min:0'],
            'documentation_expense' => ['nullable', 'numeric', 'min:0'],
            'transportation_expense' => ['nullable', 'numeric', 'min:0'],
            'insurance_expense' => ['nullable', 'numeric', 'min:0'],
            'brokerage' => ['nullable', 'numeric', 'min:0'],
            'holding_cost' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'target_profit' => ['nullable', 'numeric', 'min:0'],
            'final_negotiated_price' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
