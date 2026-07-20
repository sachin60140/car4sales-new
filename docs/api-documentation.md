# Car4Sales — API Documentation (v1)

Base URL: `/api/v1`. Auth: Laravel Sanctum bearer tokens (one token per device). All responses use
the envelope:

```json
{ "success": true, "message": "Operation completed successfully.", "data": {}, "meta": {} }
```

Errors use the same envelope with `success: false`; validation errors carry `meta.errors`
(`field → [messages]`). HTTP codes: 200/201 success, 401 unauthenticated, 403 forbidden,
404 not found, 409 conflict (idempotency/duplicates), 422 validation/invalid transition,
429 rate-limited.

## Conventions

- **Pagination**: `?page=`, `?per_page=` (max 100). `meta.pagination = {current_page, per_page, total, last_page}`.
- **Filtering**: `?filter[status]=new&filter[branch_id]=2` (whitelisted per endpoint).
- **Sorting**: `?sort=-created_at,name`.
- **Idempotency**: mutating endpoints accept `Idempotency-Key` header; repeats return the original
  response (409 if still processing). Required for mobile offline-sync submissions.
- **Rate limits**: `auth` endpoints 10/min per IP; general API 120/min per user.
- **Scoping**: every list/lookup is filtered by the caller's role data-scope; sensitive fields
  (KYC, bank details, purchase cost, profit) are omitted unless the matching permission is held.
- **Versioning**: URI-versioned; breaking changes ⇒ `/api/v2`.

## Phase 1 endpoints (implemented)

| Method | Path | Description |
|---|---|---|
| POST | `/auth/login` | `{email, password, device_name, device_uuid?, platform?, app_version?, fcm_token?}` → token + user + permissions; requires `access-mobile` permission; writes `user_devices` + `login_histories` |
| POST | `/auth/logout` | Revokes current token, marks device revoked |
| GET | `/auth/me` | Profile + roles + permissions + branch/department/team |
| POST | `/auth/device/push-token` | `{device_uuid, fcm_token}` update |
| GET | `/dashboard` | Role-aware counters (stub until feature phases land) |

## Phase 2 endpoints (implemented)

| Method | Path | Description |
|---|---|---|
| GET | `/purchase-leads` | List (scoped, paginated, `?search=`, `?filter.status=`) |
| POST | `/purchase-leads` | Create a lead (auto lead number + verification checklist); `source=mobile` |
| GET | `/purchase-leads/{id}` | Lead detail with follow-ups + allowed transitions |
| POST | `/purchase-leads/{id}/followups` | Add a manual follow-up |
| POST | `/purchase-leads/{id}/transition` | Validated status change (422 on illegal transition) |
| GET | `/inspections` | Inspector's assigned inspections |
| GET | `/inspections/{id}` | Inspection with sections + items |
| PATCH | `/inspections/{id}` | Save section ratings/repair estimates (blocked once locked) |
| POST | `/inspections/{id}/submit` | Submit + lock, totals repair estimate, advances lead |
| POST | `/inspections/{id}/media` | Multipart photo/video upload (compressed + thumbnailed) |
| GET | `/approvals` | Central approval inbox filtered to the caller's pending steps |
| POST | `/approvals/{id}/decide` | `{decision: approve\|reject}`; final purchase approval spins up the purchase record |

## Phase 8 endpoints (implemented)

| Method | Path | Description |
|---|---|---|
| GET | `/deliveries` | List (scoped, paginated, `?filter.status=`) |
| POST | `/deliveries` | Open a delivery for a booking (idempotent per booking); auto-derives system checks |
| GET | `/deliveries/{id}` | Delivery detail with the approval checklist + completion status |
| POST | `/deliveries/{id}/checks` | Refresh auto-checks and set the manual checklist items |
| POST | `/deliveries/{id}/approve` | Approve (422 unless every checklist item is satisfied); vehicle → delivery-pending |
| POST | `/deliveries/{id}/complete` | Record handover; delivers vehicle/booking/lead and spawns the RTO case |
| GET | `/rto-cases` | List (scoped, paginated, `?filter.status=`, `?mine=1`) |
| GET | `/rto-cases/{id}` | Case detail with allowed transitions, movements, expenses, holds |
| POST | `/rto-cases/{id}/transition` | Validated 17-state transfer transition (422 on illegal move) |
| POST | `/rto-cases/{id}/movements` | Record document custody hand-off (defaults source to last holder) |
| POST | `/rto-cases/{id}/expenses` | Record an RTO expense head |

## Phase 9 endpoints (implemented)

| Method | Path | Description |
|---|---|---|
| GET | `/notifications` | The caller's notifications (`?unread=1`, paginated); `meta.unread` carries the badge count |
| POST | `/notifications/{id}/read` | Mark one notification read (owner only) |
| POST | `/notifications/read-all` | Mark every unread notification read |
| GET | `/reports` | Reports the caller may run (with each report's filter schema) |
| GET | `/reports/{report}` | Run a report (`?date_from=&date_to=&branch_id=`) returning columns, rows, summary and chart |

`GET /dashboard` now also returns `notifications_unread` and scoped operational widgets
(`bookings_30d`, `deliveries_pending`, `rto_open`). Push tokens continue to register via
`POST /auth/device/push-token`; the push channel fans out to those tokens.

Each group follows the same REST shape: `GET /` (list, filtered+paginated), `POST /` (create),
`GET /{id}`, `PATCH /{id}`, plus explicit action endpoints (`POST /{id}/transition`,
`POST /{id}/assign`, `POST /{id}/approve` …) rather than overloading PATCH. File uploads use
multipart with server-side compression + thumbnailing; downloads return temporary signed URLs,
never raw storage paths.

## Auth flow (mobile)

1. `POST /auth/login` with device metadata → `{token, user, permissions[], scopes}`.
2. Token stored in secure storage; sent as `Authorization: Bearer`.
3. 401 → app clears token, returns to login. Device revocation (web panel) deletes the token
   server-side, forcing re-login on next request.
4. FCM token refresh → `POST /auth/device/push-token`.
