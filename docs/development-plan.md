# Car4Sales — Development Plan

Multi-branch pre-owned car dealership management platform: internal web panel, public website,
REST API, and Flutter mobile app on a single Laravel modular monolith.

## 1. Repository Assessment (2026-07-20)

The repository was empty at project start (no prior code to preserve). The project was scaffolded
from `laravel/vue-starter-kit v1.0.2`, which provides:

| Area | Provided by starter kit |
|---|---|
| Framework | Laravel 12.64 (PHP ^8.2), Inertia Laravel 2 |
| Frontend | Vue 3.5 + TypeScript, Inertia 2, Tailwind CSS, radix-vue (shadcn-vue components), Ziggy |
| Auth | Session auth: login, register, forgot/reset password, email verification, confirm password, profile & password settings, appearance settings |
| Layouts | `AppLayout` (sidebar shell), `AuthLayout`, settings layout |
| Tooling | Vite 6, ESLint, Prettier, Pint, vue-tsc, SSR build script (`build:ssr`) |

Added on top: `spatie/laravel-permission`, `spatie/laravel-activitylog`, `laravel/sanctum`,
`pestphp/pest` + `pest-plugin-laravel`.

### Environment deviations from the mandated stack

| Mandated | Actual (local dev) | Rationale / upgrade path |
|---|---|---|
| Laravel 13 + PHP 8.4 | Laravel 12 + PHP 8.2.12 (XAMPP) | The local runtime is PHP 8.2; Laravel 13 requires a newer PHP. Laravel 12 uses the identical application structure — upgrading is a `composer.json` constraint bump once PHP ≥ 8.3/8.4 is installed. No architecture decisions depend on the framework minor version. |
| MySQL 8.4 | MariaDB 10.4.32 (XAMPP) locally; MySQL 8.4 in production | `mariadb` driver configured. Migrations avoid MySQL-8-only features (no functional indexes, no CHECK-dependent logic) so both engines work. |
| Redis + Horizon | `database` queue/cache locally; Redis + Horizon in production | Horizon requires `ext-pcntl` (unavailable on Windows). Queue code is driver-agnostic; production `.env` switches `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` and deploys Horizon. |
| Laravel Reverb | Deferred to Phase 9 (notifications) | Broadcasting config stays driver-agnostic (`BROADCAST_CONNECTION`). |
| S3 | `local` (private) disk locally, S3 disk in production | All file access goes through `Storage::disk('private')` + temporary signed URLs, so the swap is config-only. |

## 2. Architecture

Modular monolith. Domain code lives in `app/Domain/<Module>/` with sub-namespaces
`Models`, `Enums`, `Data`, `Actions`, `Services`, `Policies`, `Events`, `Jobs`, `Notifications`.
HTTP layer stays in `app/Http/Controllers/<Area>/` (Area = `Admin`, `Api\V1`, `Public`) with form
requests in `app/Http/Requests` and API resources in `app/Http/Resources` — controllers are thin
and delegate to Domain actions/services.

Route groups:

| Group | File | Middleware | Purpose |
|---|---|---|---|
| Public website | `routes/public.php` | `web` | SEO pages, listings, enquiry forms |
| Employee panel | `routes/web.php` + feature route files | `web`, `auth`, `verified` | Inertia admin panel |
| API v1 | `routes/api_v1.php` | `api`, `auth:sanctum` | Flutter app + integrations |

Cross-cutting concerns:

- **Workflow state machines** — every lifecycle entity (purchase lead, sales lead, booking, RTO
  case…) has a PHP enum implementing `HasTransitions`; transitions are validated centrally and every
  change writes a `*_status_histories` row.
- **Reference numbers** — `NumberSequenceService` issues per-type (optionally per-branch)
  transaction-safe sequences via `SELECT … FOR UPDATE` on `number_sequences`.
- **Audit** — `spatie/laravel-activitylog` on all workflow models + dedicated history tables for
  status, approvals, payments; login/device/IP history in dedicated tables.
- **Authorization** — Spatie roles/permissions + policies + `DataScope` (per role-module) resolved
  by `ScopeService` into query constraints. All enforcement server-side.
- **Money** — stored as `DECIMAL(14,2)`; no floats.
- **Deletes** — soft deletes on master/workflow data; financial postings are reversed, never deleted.

## 3. Phase Plan

| Phase | Scope | Status |
|---|---|---|
| 1. Foundation | Auth review, branches, departments, teams, employees, RBAC, number sequences, audit logs, admin layout, API v1 foundation (Sanctum, envelope, devices), seeders, tests | **Done** |
| 2. Purchase | Purchase leads + follow-ups, inspections, seller KYC, document verification, valuation, approvals, agreement PDF, seller payments, possession, auto stock entry | **Done** |
| 3. Inventory & Workshop | Stock, media, movements, transfers, job cards, expenses, pricing, publication flags | **Done** |
| 4. Public Website | All public pages, filters, SEO + JSON-LD, sitemap/robots, enquiry → lead creation, OTP + rate limiting, admin enquiries inbox | **Done** (SSR deferred — client `<Head>` meta + JSON-LD + sitemap deliver SEO; enable Inertia SSR at production hardening) |
| 5. CRM & Telecaller | Customers, sales leads (15-state workflow), assignment history, manual call logs with outcome rules, follow-up queue, telecaller reports, enquiry→lead conversion, mobile telecaller API | **Done** |
| 6. Sales & Booking | Visits, test drives, bookings (12-state) with row-locked double-booking prevention, discount approval via approval engine, cancellation + refund (approval-gated), booking payments, mobile sales API | **Done** |
| 7. Finance & Payments | Lenders, finance applications (13-state) + disbursement, reversal-only customer ledger, payments→ledger + receipts, invoice PDF, payment accounts, mobile finance API | **Done** |
| 8. Delivery & RTO | Delivery approval checklist (auto + manual), row-safe handover → vehicle/booking/lead Delivered, auto-spawned RTO transfer case (17-state), document custody tracking, RTO expenses + payment holds, RC upload, delivery challan PDF, mobile delivery + RTO API | **Done** |
| 9. Reports & Notifications | Multi-channel notifications (in-app + mail/SMS/WhatsApp/push behind pluggable drivers), event wiring across the workflow engine, header bell + inbox + mobile API, report engine (6 scoped reports) with CSV/PDF export, enriched sales/finance/delivery dashboards + collections trend, scheduled daily manager digest | **Done** (SMS/WhatsApp/FCM ship as log-driver stubs — swap the driver in production; enable the scheduler with `schedule:work` off XAMPP) |

### Post-phase feature additions

| Feature | Scope | Status |
|---|---|---|
| Purchase by Vendor | Self-service **vendor partner portal** (`/vendor`): register → admin activates → submit vehicles with vehicle details, gallery + damaged-part images (multi-file upload), a Pass/Fail/NA condition checklist with per-item ratings and an **auto-computed, read-only overall rating**, and an expected amount. Staff review queue (`/admin/vendor-submissions`) + partner activation (`/admin/vendor-partners`); **approval creates a purchase lead** (`source = vendor`) that enters the existing purchase pipeline. Vendor Partner role, scoped policies, multi-channel notifications on register/submit/review. | **Done** |
| Vendor settlement | Post-approval settlement flow: approval opens the settlement (`settlement_status`) and the vendor **downloads a pre-filled agreement PDF** (Vehicle Purchase Agreement + Form 29 & 30) — then **requests payment** with bank details and a cancelled-cheque upload. Staff **record the payment** (amount, mode, reference, date) with a payment-proof screenshot, moving the submission to *Paid*. Admin submission page surfaces the agreement, vendor bank + cheque, and paid details + proof. Notifications to reviewers on request and to the vendor on payment. | **Done** |

Flutter app work is embedded in each phase (foundation in Phase 1 docs; feature screens land with
their backend phase). The mobile project lives in a separate repository/folder `car4sales-mobile`
(see `docs/mobile-app-plan.md`).

## 4. Definition of Done (per feature)

Migration → models/relations → validation → policy + scope → thin controller → web UI (loading/
empty/error states) → API + resource (where mobile-relevant) → audit trail → Pest tests green →
`vue-tsc` green → `npm run build` green → docs updated. No dead buttons, no placeholders.

## 5. Conventions

- PHP: PSR-12 via Pint; enums for all statuses; actions named `VerbNounAction`.
- DB: snake_case, plural tables, `*_id` FKs, `DECIMAL(14,2)` money, `timestamps()` + `softDeletes()`
  on domain tables, status history tables named `<entity>_status_histories`.
- Frontend: pages in `resources/js/pages/<area>/<Module>/`, shared UI in `components/ui`,
  feature components in `components/<module>/`. TypeScript types in `resources/js/types`.
- API: enveloped responses `{success, message, data, meta}`; errors use the same envelope with
  `errors` in `meta`; versioned under `/api/v1`.
- Tests: Pest feature tests per module in `tests/Feature/<Module>/`.
