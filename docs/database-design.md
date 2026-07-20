# Car4Sales — Database Design

Engine: MySQL 8.4 (production) / MariaDB 10.4 (local). Charset `utf8mb4`, collation
`utf8mb4_unicode_ci`. Conventions: plural snake_case tables, `id` BIGINT UNSIGNED PK,
`*_id` FKs with `ON DELETE RESTRICT` (unless noted), `DECIMAL(14,2)` for money,
`timestamps()` everywhere, `softDeletes()` on domain data. Status columns are string-backed
PHP enums. Every workflow table has a companion `*_status_histories` table
(`id, <entity>_id, from_status, to_status, changed_by, remarks, created_at`).

## 1. Foundation (Phase 1 — implemented)

### branches
`id, code (unique), name, slug, address, city, state, pin_code, phone, email, latitude, longitude, gst_number, is_active, sort_order, settings JSON, timestamps, soft_deletes`

### departments
`id, code (unique), name, slug, description, is_active, sort_order, timestamps, soft_deletes`
Seeded with the 14 standard departments; fully configurable (CRUD).

### teams
`id, branch_id FK, department_id FK, name, code (unique), team_leader_id FK users nullable, is_active, timestamps, soft_deletes`

### users
Starter-kit table extended with:
`branch_id FK nullable, department_id FK nullable, team_id FK nullable, phone, is_active bool default 1, last_login_at, password_changed_at, force_password_change bool, soft_deletes`

### employee_profiles
`id, user_id FK unique, employee_code (unique), designation, date_of_joining, date_of_leaving nullable, dob, gender, address, city, state, pin_code, emergency_contact_name, emergency_contact_phone, blood_group, photo_path, id_proof_type, id_proof_number, reports_to FK users nullable, timestamps, soft_deletes`

### Spatie tables
`roles, permissions, model_has_roles, model_has_permissions, role_has_permissions` (vendor
migration). Roles carry extra columns via `role_meta`:

### role_meta
`id, role_id FK unique, data_scope enum(all|selected_branches|own_branch|own_department|own_team|assigned|own|read_only), scope_branch_ids JSON nullable, description, is_system bool`

### approval_limits
`id, role_id FK, module (string), max_amount DECIMAL(14,2) nullable, requires_escalation bool, timestamps`

### number_sequences
`id, key (e.g. purchase_lead), branch_id FK nullable, prefix, year, next_number BIGINT, padding TINYINT default 6, timestamps`
Unique on `(key, branch_id, year)`. Consumed under `lockForUpdate()` inside the caller's transaction.

### user_devices
`id, user_id FK, device_uuid, device_name, platform enum(android|ios|web), app_version, fcm_token nullable, ip_address, last_used_at, revoked_at nullable, timestamps`
Unique `(user_id, device_uuid)`.

### login_histories
`id, user_id FK, ip_address, user_agent, device_uuid nullable, guard (web|api), event enum(login|logout|failed|forced_logout), created_at`

### activity_log (spatie/laravel-activitylog)
Vendor structure; `subject` morph + `causer` morph + `properties` JSON + `event` + `batch_uuid`.
Used for record-change history on every domain model via the `LogsActivity` trait.

### personal_access_tokens (Sanctum)
Vendor structure; token per device login, name = device UUID.

## 2. Purchase side (Phase 2)

- **sellers** — `seller_code, type enum(individual|dealer|company), name, mobile (indexed), alt_mobile, email, address, city, state, pin_code, gst_number, pan_number, bank_* (account_name, account_number encrypted, ifsc, bank_name), is_blacklisted, remarks`
- **seller_documents** — `seller_id, purchase_lead_id nullable, type enum(aadhaar|pan|address_proof|photo|photo_with_vehicle|cancelled_cheque|signature|gst|authorisation_letter|relationship_proof|poa|owner_declaration|other), file_path, status enum(pending|received|verified|rejected|expired|not_applicable), verified_by, verified_at, rejection_reason, meta JSON`
- **purchase_leads** — `lead_number (PL-…), seller_id nullable, seller_name, seller_type, mobile, alt_mobile, email, address, city, pin_code, source, registration_number (indexed), make, model, variant, manufacturing_year, fuel_type, transmission, odometer_km, expected_price, loan_status enum(none|active|closed_pending_noc), inspection_location, assigned_to FK users, branch_id, priority enum(low|normal|high|hot), next_follow_up_at, status (18-state enum), lost_reason, remarks, utm JSON, meta JSON`
- **purchase_followups** — `purchase_lead_id, user_id, contact_mode enum(call|whatsapp|visit|other), outcome, remarks, next_follow_up_at, created_at`
- **vehicle_inspections** — `inspection_number (INS-…), purchase_lead_id, inspector_id, branch_id, scheduled_at, started_at, completed_at, location, odometer_km, overall_grade enum(A|B|C|D), result enum(recommended|recommended_with_repairs|management_approval|not_recommended), total_repair_estimate, remarks, locked_at, reviewed_by, reviewed_at, signature_path, status`
- **inspection_sections** — `vehicle_inspection_id, key (exterior|interior|engine|…), rating TINYINT nullable, status enum(pass|fail|na), remarks, repair_estimate`
- **inspection_items** — `inspection_section_id, checklist_item_id nullable, label, value enum(ok|attention|fail|na), severity enum(minor|major|critical), remarks, repair_estimate`
- **inspection_checklist_items** (config) — `section_key, label, sort_order, is_active`
- **inspection_media** — `vehicle_inspection_id, inspection_item_id nullable, type enum(photo|video), file_path, thumbnail_path, panel_marker JSON nullable, captured_at, meta JSON`
- **vehicle_verifications** — per purchase lead; one row per document type from §11 list: `purchase_lead_id, type, status enum(pending|received|verified|rejected|expired|not_applicable), file_path, number, valid_till, verified_by, verified_at, remarks`
- **vehicle_valuations** — `purchase_lead_id, market_price, expected_retail_price, seller_expected_price, repair_estimate, rto_expense, documentation_expense, transportation_expense, insurance_expense, brokerage, holding_cost, other_costs, target_profit, recommended_price (computed & stored), final_negotiated_price, expected_gross_profit, expected_net_profit, expected_margin_pct, prepared_by, approved_by nullable, status`
- **purchase_approvals** — see approval engine (§9); plus `requested_amount, recommended_amount, approved_amount, reasons JSON`
- **vehicle_purchases** — `purchase_number (PUR-…), purchase_lead_id, seller_id, vehicle_id nullable (set at stock entry), agreed_price, agreement_document_id, purchased_at, status`
- **seller_payments** — `payment_number, vehicle_purchase_id, type enum(token|advance|full|balance|hold|loan_closure|bank_payment|owner_payment|brokerage|adjustment), amount, payment_account_id, method, reference_number, proof_path, recipient_type enum(seller|bank|registered_owner|broker), recipient_details JSON, status enum(draft|pending_approval|approved|paid|reversed), created_by (maker), approved_by (checker), reversed_by, reversal_of FK self, remarks`
- **vehicle_possessions** — one per purchase; checklist booleans from §15, `odometer_km, fuel_level, seller_signature_path, employee_signature_path, possessed_at, photos via media table`

## 3. Inventory (Phase 3)

- **vehicles** (stock) — `stock_number (STK-…), vehicle_purchase_id, registration_number (unique among non-deleted), chassis_number (unique), engine_number (unique), make, model, variant, manufacturing_year, registration_year, registration_state, fuel_type, transmission, body_type, color, odometer_km, ownership_serial, insurance_status, insurance_valid_till, purchase_price, landed_cost, minimum_selling_price, asking_price, branch_id, parking_location, inspection_grade, refurb_required bool, status (15-state enum), published_web bool, published_mobile bool, slug (unique, SEO), title, description, key_features JSON, is_featured, reserved_booking_id nullable`
- **vehicle_documents / vehicle_media / vehicle_locations / vehicle_movements / vehicle_expenses / vehicle_prices / vehicle_status_histories** — as named; `vehicle_expenses.approved` amounts roll up into `vehicles.landed_cost`; `vehicle_prices` keeps price-change history with `changed_by`.
- **workshop_jobs** — job card: `job_number, vehicle_id, vendor_id nullable, type enum(internal|external), estimate_total, approved_total, actual_total, started_at, expected_at, completed_at, payment_status, qc_status, qc_by`
- **workshop_job_items** — `workshop_job_id, defect, work_type enum(part|labour), description, estimate, approved_amount, actual_amount, status`

## 4. CRM / Sales (Phases 5–6)

- **customers** — `customer_code, name, mobile (indexed), alt_mobile, email, address, city, state, pin_code, occupation, dob, kyc_status, meta`
- **customer_documents** — same shape as seller_documents.
- **sales_leads** — `lead_number (SL-…), customer_id nullable, name, mobile, city, budget_min, budget_max, interested_vehicle_id nullable, preferences JSON, finance_required bool, exchange_required bool, source enum(§18 list), campaign, utm JSON, branch_id, telecaller_id, sales_executive_id, priority, next_follow_up_at, status (12-state enum), lost_reason_id, remarks`
- **lead_lost_reasons** (config), **lead_assignments** (history of telecaller/SE assignment with `assigned_by, from_user, to_user, reason`), **lead_followups** (`sales_lead_id, user_id, channel, call_outcome enum(§18), remarks, next_follow_up_at, duration_seconds nullable`), **lead_activities** (unified timeline), **lead_status_histories**.
- **customer_visits** — `visit_number (VIS-…), sales_lead_id, branch_id, scheduled_at, confirmed bool, arrived_at, attended_by, outcome, next_action, remarks, status`
- **test_drives** — `td_number (TD-…), sales_lead_id, vehicle_id, driving_licence_path, start_at, end_at, start_odometer, end_odometer, fuel_level, route, accompanied_by, customer_signature_path, damage_acknowledged bool, feedback, status`

## 5. Booking / Finance / Payments (Phases 6–7)

- **bookings** — `booking_number (BKG-…), sales_lead_id, customer_id, vehicle_id, selling_price, booking_amount, discount_amount, discount_approved_by nullable, payment_mode enum(cash|finance), exchange_adjustment, delivery_promised_at, telecaller_id, sales_executive_id, branch_id, accessories_promised JSON, terms, customer_signature_path, status (12-state enum)` — partial unique index guarantees one active booking per vehicle; confirmation runs inside `lockForUpdate` on the vehicle row.
- **booking_approvals, booking_payments, booking_cancellations, refunds** — as named; refunds require approval and post to the ledger.
- **finance_applications** — `application_number, booking_id, applicant JSON, co_applicant JSON, guarantor JSON, income JSON, loan_amount, down_payment, lender_id, lender_application_number, sanction_amount, interest_rate, tenure_months, emi, rejection_reason, status (13-state enum)`
- **lenders** (config), **finance_documents**, **finance_status_histories**, **lender_submissions**, **disbursements**.
- **customer_ledgers** — one per booking/customer; **ledger_entries** — `ledger_id, type enum(debit|credit), head enum(selling_price|booking_amount|down_payment|finance_amount|exchange|discount|insurance|accessories|rto_charges|other|refund), amount, reference morph, reversal_of FK self nullable, posted_by, posted_at` — reversal-only correction.
- **payments** — customer receipts: `payment_number (PAY-…), booking_id, direction enum(in|out), method, amount, account_id, reference, proof_path, status(draft|approved|reversed), maker/checker fields`
- **payment_reversals**, **invoices** (`invoice_number, booking_id, totals, pdf document_id`), **receipts**.
- **payment_accounts** (config: cash drawers/bank accounts per branch).

## 6. Delivery / RTO (Phase 8)

- **deliveries** — `delivery_number (DLV-…), booking_id, approval checklist booleans (§24), scheduled_at, delivered_at, odometer, fuel_level, customer_photo_path, delivery_photo_path, customer_signature_path, employee_signature_path, status`
- **delivery_checklists, delivery_documents, delivery_approvals**.
- **rto_cases** — `rto_number (RTO-…), vehicle_id, booking_id, seller_id, buyer customer_id, from_rto, to_rto, sale_date, delivery_date, assigned_to, agent_vendor_id, expected_completion, application_number, hold_amount, status (17-state enum), rc_copy_path`
- **rto_documents, rto_status_histories, rto_document_movements** (`document, from_holder, to_holder, moved_at, remarks`), **rto_expenses, rto_holds**.

## 7. Documents & PDF (cross-phase)

- **document_templates** — `key, name, module, engine enum(blade|html), requires_admin_approval bool, is_active`
- **document_template_versions** — `template_id, version, body LONGTEXT, fields JSON, approved_by, approved_at`
- **generated_documents** — `document_number, template_version_id, subject morph, file_path, qr_payload, generated_by, meta JSON` — immutable snapshot of every generated PDF.

## 8. Public website (Phase 4)

- **public_enquiries** — unified: `type enum(vehicle|test_drive|finance|callback|contact|sell_car), name, mobile, email, city, vehicle_id nullable, message, consent bool, otp_verified_at, source, utm JSON, ip, branch_id nullable, sales_lead_id nullable (created lead), purchase_lead_id nullable, status`
- **sell_car_requests** — full §6 capture, FK to created purchase_lead.
- **vehicle_favourites** (`session/customer`, vehicle), **testimonials, faqs, pages (CMS), seo_metadata, website_banners**.
- **otp_verifications** — `mobile, code_hash, expires_at, attempts, verified_at`.

## 9. Approvals / Notifications / Audit (cross-phase)

- **approval_requests** — `approval_number (APR-…), module, subject morph, requested_by, amount nullable, reason, attachments JSON, status enum(pending|approved|rejected|cancelled), decided_at`
- **approval_steps** — `approval_request_id, sequence, role_id nullable, user_id nullable, status, acted_by, acted_at, remarks` — chain built from `approval_limits`.
- **notifications** — Laravel notifications table (+ FCM via channels).
- **exports** — `user_id, report_key, filters JSON, format, file_path, status, row_count` (queued export audit).
- **audit** — `activity_log` + `login_histories` + per-entity status histories (above).

## 10. Integrity rules

1. Stock creation, booking confirmation, payment posting, sequence consumption: all inside DB
   transactions with row locks.
2. Duplicate stock prevented by unique indexes on `registration_number`, `chassis_number`,
   `engine_number` (scoped to non-deleted rows via generated column trick or application-level
   check + unique index).
3. Financial rows (`ledger_entries`, `payments`, `seller_payments`) are append-only; corrections
   create reversal rows referencing the original.
4. Status columns only change through `WorkflowService::transition()` which validates the enum's
   allowed-transition map and writes history atomically.
