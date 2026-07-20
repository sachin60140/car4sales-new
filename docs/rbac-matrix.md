# Car4Sales — RBAC Matrix

Implementation: `spatie/laravel-permission` (roles + granular permissions), Laravel policies per
model, and a **data-scope** layer resolved per role. All checks are server-side; the UI only hides
what the backend already forbids.

## 1. Permission naming

`<module>.<action>` — e.g. `purchase-leads.view`, `bookings.approve`, `vehicles.view-purchase-cost`.

### Modules
`branches, departments, teams, employees, roles, sellers, purchase-leads, inspections,
vehicle-verifications, valuations, purchase-approvals, vehicle-purchases, seller-payments,
possessions, vehicles (stock), refurbishment, customers, sales-leads, telecalling, visits,
test-drives, bookings, finance, payments, ledgers, deliveries, rto-cases, documents, templates,
approvals, public-website, reports, notifications, audit, settings`

### Actions
`view, create, update, delete, assign, reassign, approve, reject, cancel, reopen, print, download,
export, view-kyc, view-bank-details, view-purchase-cost, view-profit, reverse-payment,
access-mobile, access-reports`

Only meaningful `module × action` pairs are generated (registry:
`app/Domain/RolesPermissions/Support/PermissionRegistry.php`). The registry is the single source of
truth; the seeder syncs it.

## 2. Data scopes

Stored per role in `role_meta.data_scope`; the most permissive scope among a user's roles wins.

| Scope | Meaning |
|---|---|
| `all` | All company records |
| `selected_branches` | Branch IDs listed in `role_meta.scope_branch_ids` |
| `own_branch` | Records of user's branch |
| `own_department` | Own branch + own department records |
| `own_team` | Records assigned to members of the user's team |
| `assigned` | Records assigned to the user |
| `own` | Records created by the user |
| `read_only` | View-only across allowed scope (blocks all mutating permissions) |

`ScopeService::apply($query, $user, $module)` translates the winning scope into query constraints
(branch_id / assigned_to / created_by columns). Policies call it via the `ScopedByRole` trait.

## 3. Role → default configuration

| Role | Department | Scope | Highlights |
|---|---|---|---|
| Super Admin | — | all | Every permission (`Gate::before` bypass) |
| Director | Management | all | All view/approve/report + profit & cost visibility; no destructive settings |
| Owner | Management | all | Same as Director |
| Administrator | Administration | all | Master data, employees, roles, templates, settings; no profit-sensitive approvals |
| Branch Manager | Management | own_branch | Approve within branch: purchases, discounts, deliveries, transfers; view profit & cost |
| Purchase Manager | Purchase | own_branch | Purchase leads/inspections/valuations manage + approve within limit; view purchase cost |
| Purchase Executive | Purchase | assigned | Purchase leads CRU, follow-ups, KYC capture, request approvals |
| Inspector | Inspection | assigned | Inspections CRU + submit; no pricing visibility |
| Inventory Manager | Inventory | own_branch | Stock manage, movements, transfers, publication, price update; view purchase cost |
| Inventory Executive | Inventory | own_branch | Stock view/update, media, movements; no cost/profit |
| Workshop Manager | Workshop | own_branch | Job cards CRUA, vendor mgmt, expense submit |
| Telecalling Manager | Telecalling | own_branch | Lead assign/reassign, telecaller reports, targets |
| Team Leader | Telecalling/Sales | own_team | Team queue, reassign within team, team reports |
| Telecaller | Telecalling | assigned | Assigned leads, call logs, follow-ups, visit/TD scheduling |
| Sales Manager | Sales | own_branch | Assign leads, discount/booking approvals within limit, selling-price control, delivery approval |
| Sales Executive | Sales | assigned | Assigned leads, visits, test drives, bookings, KYC upload |
| Finance Manager | Finance | own_branch | Finance apps full, lender mgmt, disbursements |
| Finance Executive | Finance | assigned | Finance apps CRU, document upload |
| Accounts Manager | Accounts | own_branch | Ledgers, payments approve, reversals, invoices; view bank details |
| Cashier | Accounts | own_branch | Payment entry (maker only), receipts print |
| Delivery Manager | Delivery | own_branch | Delivery approvals, checklist review |
| Delivery Executive | Delivery | assigned | Delivery checklist execution |
| RTO Manager | RTO | own_branch | RTO cases full, expenses, holds |
| RTO Executive | RTO | assigned | Assigned RTO cases, document movement, status updates |
| Legal User | Legal & Compliance | all (read_only) + legal modules | KYC view, document view, blacklist |
| Auditor | — | all (read_only) | View + export everything incl. audit logs; zero mutations |

Sensitive-field permissions (`view-kyc`, `view-bank-details`, `view-purchase-cost`, `view-profit`,
`reverse-payment`) are **not** granted by module-view; they are explicit grants listed above and
enforced in API resources / Inertia props (fields omitted server-side without permission).

## 4. Approval limits

`approval_limits (role_id, module, max_amount, requires_escalation)` drive the approval chain
builder: Purchase Executive → Purchase Manager → Branch Manager → Director/Owner. A request whose
amount exceeds a step's `max_amount` (or matching a risk flag: owner mismatch, hypothecation,
accident, low margin, negative profit, missing docs) escalates to the next step automatically.

## 5. Enforcement points

1. **Route middleware** — `auth`, `role_or_permission` for coarse gates.
2. **Policies** — every model; `viewAny/view/create/update/delete` + custom (`approve`, `assign`,
   `reverse`…). Registered in `AuthServiceProvider`, used by web + API identically.
3. **ScopeService** — query-level scoping for lists and lookups.
4. **FormRequest::authorize** — action-level re-check.
5. **Resources/props** — field-level stripping of sensitive attributes.
6. **Mobile** — same policies via Sanctum guard: `access-mobile` permission required at login.
