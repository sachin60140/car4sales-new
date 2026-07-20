# Car4Sales — Deep QA & Flow Verification Report

**Date:** 2026-07-20 · **Environment:** XAMPP (PHP 8.2, MariaDB 10.4, DB `car4sales_new`), local · **Build:** all 9 phases complete

## 1. Scope

Full-stack verification after Phase 9: automated suite, end-to-end business-flow execution against the
live database, HTTP sweep of every admin/public route, browser console audit, static code sweep, and a
targeted bug hunt. All QA entities were tagged and removed; the database was left in demo-only state.

## 2. Results at a glance

| Layer | Result |
|---|---|
| Pest suite | **182 passed** (643 assertions), 0 failed |
| E2E lifecycle flows (live DB) | **40/40 assertions passed** across 3 flows |
| Admin routes (authenticated) | 32/32 return 200 — list, workbench and report pages |
| Detail pages (real IDs) | 7/7 return 200 (booking, delivery, RTO, finance, leads, stock card) |
| Public site | 10/10 routes 200 incl. sitemap/robots; car detail pages render |
| Browser console | 0 errors across the sweep |
| Static sweep | no `dd()`/`dump()`/`console.log`/TODO leftovers; no `env()` outside config |
| TypeScript + build | `vue-tsc` clean; production build succeeds |

## 3. End-to-end flows executed (live MariaDB, QA-tagged, self-cleaned)

**Flow A — Sale to RTO closure (23 checks):** sales lead → visit + test drive → booking → confirm
(vehicle row-locked to Booked, lead → Booking, `booking.confirmed` notification fired) → ledger opened
at ₹770,000 → partial payment → **reversal restores outstanding exactly** → full settlement to 0 →
invoice → delivery with auto-derived checklist (booking/KYC/payment/finance) → approval correctly
**blocked** until manual checks done → approve (vehicle DeliveryPending) → handover (vehicle/booking/
lead all Delivered, `delivery.completed` fired) → RTO case auto-created with buyer + from-RTO mapped →
document custody chain (source defaults to last holder) → expense → hold placed/released → **full
17-state RTO walk to Closed** with 17 history rows → transition out of Closed rejected.

**Flow B — Discount approval chain (7 checks):** ₹40,000 discount raised by a Sales Manager (limit
₹25,000) → booking parks at ApprovalPending, vehicle Reserved → 2-step chain built (SM → BM) →
`approval.requested` notification reached step-1 role users → step-1 approval keeps it pending →
step-2 (Branch Manager) confirms the booking, books the vehicle, stamps `discount_approved_by`,
and notifies the requester.

**Flow C — Cancellation & refund (7 checks):** confirmed booking with ₹25,000 paid → cancellation
requested → approved (vehicle released to sale, reservation cleared) → booking parks at
**RefundPending** → refund raised opens a single-step approval (₹20,000 ≤ Accounts Manager's ₹50,000)
→ engine approval flips refund to approved via the subject hook → pay: negative payment row, ledger
debit of exactly ₹20,000, booking terminal at Refunded. *(Positive control: paying an unapproved
refund was correctly rejected by the guard.)*

## 4. Bugs found & fixed

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | **High (production-latent)** | `notification_deliveries.destination` is VARCHAR(255) but the push channel writes comma-joined FCM tokens (≤512 chars each). On MariaDB strict mode the insert throws, and because the failure path re-writes the same oversized value, the exception escapes the channel and **rolls back the business transaction that triggered the notification** (e.g. an approval opening). Masked in dev because the log driver skips with no tokens. | Destination truncated to 255 chars in `NotificationChannel::record()` — an audit row can never abort a business operation. Regression test added (3 devices × 400-char tokens → delivery `sent`, destination ≤255). |
| 2 | Low (demo quality) | Demo bookings consumed **all** sale-eligible vehicles, so the public website listed only reserved cars and zero available stock. | Seeder vehicle pool enlarged to 14 (8 sale-eligible); after bookings the public lot keeps ~4 available cars. Verified: `/cars` shows "7 cars found", detail pages 200. |

**Non-bugs confirmed during the hunt** (checked and cleared):
- `reports/{report}` catch-all does **not** shadow the older `/admin/reports/telecaller` (registration order verified statically and at runtime).
- The car-detail 404 for a delivered vehicle is correct behaviour — sold stock leaves the public catalog.
- `Booking` post-cancel state is intentionally `RefundPending` (not `Cancelled`) when a refund is due.
- Spatie `role()`/`permission()` scopes are guarded against missing role/permission names (no throw).

## 5. Follow-up fixes applied (post-report)

1. **Queued outbound notification fan-out — DONE.** The in-app "database" channel is still written
   synchronously (instant inbox), but mail/SMS/WhatsApp/push now fan out through a queued
   `DeliverNotification` job so slow network I/O never holds a domain transaction open. The `database`
   queue connection is set `after_commit => true` so a job dispatched inside a transaction only runs
   after commit. Tests use the `sync` queue (runs inline). New tests assert the job is queued and that
   nothing is queued when no outbound channel is enabled.
2. **One-active-delivery-per-booking enforced at the DB — DONE.** A generated column
   `deliveries.active_booking_id` (= booking_id while live, NULL once cancelled/soft-deleted) carries a
   UNIQUE index, so the DB rejects a second active delivery per booking. `DeliveryAction::create`
   catches the constraint violation and returns the winning delivery, making the path fully race-safe
   and idempotent. Verified on MariaDB (blocks active dup; allows cancelled/soft-deleted) and covered
   by a new test.
3. **Test suite moved to MariaDB — DONE.** Per project rule, the suite runs on a dedicated
   `car4sales_new_test` MariaDB database (not SQLite `:memory:`), so tests exercise the same engine as
   production — generated columns, FK/restrict semantics, JSON paths and strict-mode truncation are all
   validated for real. The app DB `car4sales_new` is never touched by tests.

**Still open (documented, production-time):** set `APP_DEBUG=false`, swap notification drivers
(`C4S_SMS_DRIVER` etc.), enable Inertia SSR, run `schedule:work`, move `MAIL_MAILER` off `log`.

Suite after fixes: **185 passed / 0 failed** on MariaDB.

## 6. Verdict

The platform is functionally sound end-to-end. Every money-path invariant held under live-DB
execution: ledger reversals are exact mirrors, refunds debit precisely, approval chains gate
according to `approval_limits`, vehicles never double-book, and terminal states reject further
transitions. One latent production bug (push-token audit overflow) was found and fixed with
regression coverage; one demo-data gap was fixed. Suite: **182 passed / 0 failed**.
