# Car4Sales — Flutter Mobile App Plan

Separate repository/folder `car4sales-mobile` (Flutter ≥ 3.x, Dart 3). Employees only; menu and
data are permission-driven from the `/auth/me` payload.

## Stack

| Concern | Choice |
|---|---|
| State management | Riverpod (v2, code-gen providers) |
| Networking | Dio + interceptors (auth header, envelope unwrapping, retry, 401 handler) |
| Routing | GoRouter (auth-guarded shell routes) |
| Storage | flutter_secure_storage (token), drift/sqlite (offline drafts), shared_preferences (prefs) |
| Push | firebase_messaging + flutter_local_notifications |
| Media | camera, image_picker, flutter_image_compress; background upload via workmanager + upload queue table |
| Signatures | signature pad widget → PNG upload |
| Biometrics | local_auth (optional unlock) |

## Architecture

```
lib/
├── core/            # dio client, envelope models, error mapping, idempotency key helper,
│                    # secure storage, permission service, theme, widgets (empty/error/loading)
├── features/
│   ├── auth/        # login, device registration, biometric gate, session expiry
│   ├── dashboard/   # role-aware tiles + notifications
│   ├── purchase/    # leads, follow-ups, KYC capture, valuation submit, approval result
│   ├── inspection/  # checklist runner, camera, defect marking, signature, offline draft
│   ├── telecalling/ # queue, tap-to-dial (launches dialler only), outcomes, follow-ups
│   ├── sales/       # leads, visits, test drives, stock search, discount request, booking
│   ├── delivery/    # delivery list, checklist, photos, signatures, confirmation
│   ├── rto/         # cases, document checklist, status updates, uploads, handover
│   ├── approvals/   # central approval inbox
│   └── notifications/
└── shared/          # media picker/uploader, draft repository, sync engine
```

- **Offline drafts**: drift tables `drafts (feature, payload JSON, media refs, idempotency_key,
  status)`. Sync engine flushes on connectivity restore; server dedupes via `Idempotency-Key`.
  Draft-capable forms: inspection, seller KYC, visit remarks, delivery checklist, RTO status notes.
- **Upload queue**: media rows uploaded independently with progress + retry; drafts reference
  uploaded media IDs before final submit.
- **Permissions**: `PermissionService` caches `/auth/me` permissions; router guards + widget-level
  `Can(permission)` wrapper decide menu/actions. Server remains the authority.
- **Telephony**: `url_launcher` `tel:` only — the app never records or routes calls.

## Screens by phase

| Backend phase | Mobile deliverables |
|---|---|
| 1 | Project scaffold, login, secure token, device registration, dashboard shell, settings, push-token plumbing |
| 2 | Purchase lead list/add, follow-up, schedule inspection, KYC capture, docs capture, valuation, approval result; Inspector: checklist runner + media + signature + submit (offline) |
| 3 | Stock search + vehicle detail (permission-trimmed) |
| 5 | Telecaller queue, outcomes, follow-ups, performance |
| 6 | Sales: visits, test drives, negotiation notes, discount request, booking, KYC upload |
| 8 | Delivery checklist + confirmation; RTO case management |
| 9 | Full push-notification matrix, report snapshots |

## Testing
Widget tests for auth flow, checklist runner, draft sync engine; integration tests (patrol/
integration_test) for login → lead → follow-up and inspection offline→sync happy paths; golden
tests for core widgets.
