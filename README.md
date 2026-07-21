# Car4Sales

A production-style, multi-branch **pre-owned car dealership platform** — purchase, inventory,
CRM/sales, bookings, finance, delivery & RTO, reporting, a public website, and a self-service
**vendor-partner portal** with owner-KYC document verification.

Built as a modular monolith: **Laravel 12** (PHP 8.2) + **Inertia.js 2** / **Vue 3** + TypeScript +
**Tailwind CSS**, **MariaDB/MySQL**, **Sanctum** API auth, `spatie/laravel-permission` (RBAC) &
`activitylog`, `barryvdh/laravel-dompdf` (PDFs), and **Pest** for tests.

---

## Requirements

- **PHP 8.2+** with the usual Laravel extensions (`pdo_mysql`, `gd`, `mbstring`, `bcmath`, `fileinfo`, `zip`)
- **Composer 2**
- **Node 18+** and npm
- **MariaDB 10.4+ / MySQL 8+** (this project uses MySQL/MariaDB everywhere — including the test suite)

> Developed on **XAMPP** (PHP 8.2 / MariaDB 10.4). Any equivalent LAMP/LEMP stack works.

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Then edit **`.env`** for MySQL/MariaDB (the shipped example defaults to SQLite — change it):

```dotenv
APP_NAME=Car4Sales
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mariadb      # or: mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=car4sales_new
DB_USERNAME=root
DB_PASSWORD=

# Notifications use the database queue; keep this on `database`
QUEUE_CONNECTION=database
```

```bash
# 3. Create the databases (app + a dedicated one for tests)
mysql -uroot -e "CREATE DATABASE car4sales_new; CREATE DATABASE car4sales_new_test;"

# 4. Migrate + seed base data (roles, permissions, admin user, content, lookups)
php artisan migrate --seed

# 5. (Optional but recommended) load rich demo data — branches, employees,
#    leads, inventory, bookings, vendor submissions, the demo vendor & verifier, etc.
php artisan db:seed --class=DemoDataSeeder

# 6. Build the front-end
npm run build      # or `npm run dev` for the Vite dev server
```

## Running

```bash
php artisan serve            # http://127.0.0.1:8000
php artisan queue:work       # process queued notifications (separate terminal)
php artisan schedule:work    # optional: daily manager digest
```

Or run the whole dev stack (server + queue + logs + Vite) in one command:

```bash
composer dev
```

Key entry points:

| Area | URL |
|---|---|
| Public website | `/` |
| Staff / admin login | `/login` → `/dashboard` |
| Vendor-partner portal | `/vendor` |
| Vendor self-registration | `/vendor/register` |

## Demo logins

The **admin** comes from `migrate --seed`; the rest require `db:seed --class=DemoDataSeeder`.
All demo accounts use the password **`password`** except the admin.

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@car4sales.test` | `Admin@12345` |
| Vendor Partner (demo) | `deepak.vendor@demo.car4sales.test` | `password` |
| Document Verifier (demo) | `arman@demo.car4sales.test` | `password` |
| Other demo employees (Purchase/Sales/Inspector/…) | seeded with faker names | `password` |

> Re-running `php artisan db:seed --class=DemoDataSeeder` is idempotent — it clears and
> rebuilds the demo dataset each time.

## Testing

The suite runs against a **dedicated MySQL/MariaDB database** (`car4sales_new_test`, configured in
`phpunit.xml`) — it never touches your app database.

```bash
php artisan test
```

## Feature highlights

- **Purchase** — leads → inspection → verification → valuation → approval chain → purchase →
  possession → **auto stock entry**.
- **Vendor-partner portal** — partners register, submit vehicles (images, condition checklist,
  auto-rated), then a post-approval **owner-KYC** stage: owner details, chassis, hypothecation-aware
  documents (RC/Aadhaar front+back, PAN, cheque, …) with a **per-document verification table**, a
  **dynamic pre-filled agreement** (Form 29/30), payment to the owner's account, and
  **confirm-possession → auto stock**.
- **Inventory & workshop**, **Sales CRM** (leads, visits, test-drives, bookings), **Finance &
  payments** (reversal-only ledgers), **Delivery & RTO**, **multi-channel notifications**, and
  **reports/dashboards** with CSV/PDF export.
- **RBAC** — a single permission registry, scoped roles, an editable Roles & Permissions screen, and
  **per-employee custom permissions** (grant individual actions on top of an employee's roles).
- **API v1** (Sanctum) for the companion mobile app.

## Project layout

```
app/Domain/<Module>/{Models,Enums,Data,Actions,Services,Policies}   # domain logic
app/Http/Controllers/{Admin,Api/V1,Vendor,Public}                    # entry points
resources/js/pages/{admin,vendor,public}                             # Inertia + Vue 3 pages
database/{migrations,seeders}                                        # schema + demo data
docs/                                                                # development plan & design docs
tests/{Feature,Unit}                                                 # Pest suite (MySQL)
```

See [`docs/development-plan.md`](docs/development-plan.md) for the full phase-by-phase scope.
