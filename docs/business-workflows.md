# Car4Sales — Business Workflows

Every workflow entity has a string-backed status enum with an explicit allowed-transition map.
`WorkflowService::transition($model, $to, $user, $remarks)` validates the move, requires
permission, writes the `*_status_histories` row, fires the domain event and logs activity — all in
one transaction. Illegal transitions throw `InvalidTransitionException` (HTTP 422).

## 1. Vehicle Purchase Lifecycle

```
Purchase Enquiry → Seller Follow-Up → Vehicle Inspection → Seller KYC
→ Vehicle Document Verification → Vehicle Valuation → Purchase Approval
→ Purchase Agreement Generation → Seller Payment → Vehicle Possession
→ Automatic Stock Entry → Refurbishment → Ready for Sale
```

### Purchase lead statuses & transitions

| From | Allowed to |
|---|---|
| New | Contacted, Rejected, Seller Not Interested, Closed |
| Contacted | Inspection Scheduled, Negotiation, Seller Not Interested, Vehicle Sold Elsewhere, Closed |
| Inspection Scheduled | Inspection Completed, Contacted, Closed |
| Inspection Completed | Document Verification Pending, Rejected, Closed |
| Document Verification Pending | Seller KYC Pending, Rejected |
| Seller KYC Pending | Valuation Pending, Rejected |
| Valuation Pending | Negotiation, Rejected |
| Negotiation | Purchase Approval Pending, Seller Not Interested, Vehicle Sold Elsewhere |
| Purchase Approval Pending | Purchase Approved, Rejected, Negotiation |
| Purchase Approved | Agreement Pending |
| Agreement Pending | Payment Pending |
| Payment Pending | Possession Pending |
| Possession Pending | Purchased |
| Purchased | Closed |
| Rejected / Seller Not Interested / Vehicle Sold Elsewhere | Closed, Reopen→Contacted (permission `purchase-leads.reopen`) |

Side-effects:
- *Sell Your Car* public form ⇒ creates lead in **New**, assigns branch, notifies Purchase team.
- Inspection submit + lock ⇒ lead auto-moves to **Inspection Completed**.
- All KYC docs verified ⇒ prompt to **Valuation Pending**.
- Approval decision ⇒ **Purchase Approved** (or back to Negotiation on reject).
- Possession checklist complete ⇒ **Purchased** + `CreateStockFromPurchaseAction` runs in the
  same transaction: vehicle row (status *In Stock* or *Documents Pending*), stock number, landed
  cost = agreed price + initial expenses; duplicate reg/chassis/engine blocks the commit.

### Purchase approval chain
Requested by executive → Purchase Manager → Branch Manager → Director/Owner. Steps auto-skip when
requester already holds the step role; amounts above a role's `approval_limits.max_amount` or any
risk flag (owner mismatch, hypothecation, pending NOC, accident, high repair, low/negative margin,
missing documents) force escalation to the top step.

## 2. Vehicle Sales Lifecycle

```
Customer Lead → Telecaller Follow-Up → Customer Visit → Test Drive → Vehicle Selection
→ Negotiation → Discount Approval → Booking → Customer KYC → Cash or Finance Processing
→ Payment Collection → Delivery Approval → Vehicle Delivery → RTO Paperwork
→ RC Transfer → RC Handover → Case Closure
```

### Sales lead statuses
`New → Assigned → Contacted → Interested → Follow-Up → Visit Scheduled → Visit Completed →
Test Drive → Negotiation → Booking → Finance Processing → Delivery Pending → Delivered`
plus terminal `Lost` (requires `lead_lost_reason`), `Wrong Number`, `Duplicate`.

Rules:
- Interested ⇒ `next_follow_up_at` mandatory (enforced in FormRequest + DB not-null when interested).
- Lost ⇒ lost reason mandatory.
- Assignment changes append to `lead_assignments`; every touch appends `lead_activities`.

### Booking state machine
`Draft → Approval Pending → Confirmed → Payment Pending → Finance Pending → Ready for Delivery →
Delivered`; cancellation branch `Cancellation Requested → Cancelled → Refund Pending → Refunded /
Forfeited`.

Booking confirmation (single transaction):
1. `SELECT vehicles WHERE id=? FOR UPDATE`
2. Vehicle must be *Ready for Sale/Published/Reserved-by-this-booking* — else abort (double-booking block)
3. Discount > sales executive limit ⇒ status *Approval Pending* + approval request to Sales Manager
4. Vehicle → *Booked*, `reserved_booking_id` set; ledger opened, booking amount posted
5. Stock released only by authorised cancellation (approval + reason + refund decision).

### Delivery
Delivery approval checklist (§24 of the spec) must be fully green + manager approval before the
delivery checklist can execute. Confirmed delivery ⇒ vehicle → *Delivered*, booking → *Delivered*,
and an **RTO case auto-creates** in the same transaction.

### RTO case
`Case Created → Seller Documents Pending → Buyer Documents Pending → Signature Pending →
[NOC Required → NOC Applied → NOC Received] → Forms Prepared → Submitted →
Appearance/Inspection Pending → [Objection Raised → Objection Resolved] → Transfer Approved →
RC Printed → RC Received → RC Handed Over → Closed`
Bracketed segments are optional per case. Every original-document handover writes
`rto_document_movements` (who → whom, when, remarks).

## 3. Money rules

- Seller payments and customer payments: maker-checker (creator ≠ approver), approval before
  payout, proof attachment, reference number.
- Ledger entries and approved payments are immutable — corrections post reversal rows
  (`reversal_of`), permission `payments.reverse-payment`.
- Refunds: request → approval → payment-out → ledger credit reversal, each step audited.

## 4. Audit trail summary

| Layer | Mechanism |
|---|---|
| Field changes | activitylog (`LogsActivity`) on all domain models |
| Status changes | `*_status_histories` via WorkflowService |
| Assignments | `lead_assignments` |
| Approvals | `approval_requests` + `approval_steps` |
| Money | append-only + reversal rows |
| Access | `login_histories`, `user_devices`, export/download logs |
