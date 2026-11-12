# RestoFlow Restaurant POS

Production-oriented restaurant POS built with Laravel 13, React, and TypeScript.

## Current implementation

- Laravel API foundation with session authentication and CSRF protection
- Database-backed roles and permissions enforced by Laravel Gates
- Restaurant, zone, configurable table, order guard, and audit models
- Indoor and Outdoor seed zones with 15 initial tables
- Server-derived live floor status
- Manager table creation and safe deactivation rules
- Menu & Pricing subsystem (categories, Kitchen/Bar station routing, variants, modifier groups, tax categories, effective-dated price history with mandatory change reason, and station-scoped sold-out toggles)
- Order Core & POS Composer (single-open-order table guard, idempotency keys, optimistic version checks, server snapshot pricing, modifier customization, table transfers, order merges, and bill request workflow)
- KOT & Kitchen/Bar Display System (atomic round sending, station-based KOT splitting, price-free KDS queue, `Pending → Preparing → Ready → Served` transitions, manager sent-item voiding with bill adjustment, and 80mm thermal KOT slip print preview with logged reprints)
- Billing, Cashier Shifts, Discounts, Payments & 80mm Customer Receipt (cashier shift open/close with expected cash vs. counted cash variance & manager approval, role-enforced discounts with 5% waiter limit, split/partial payments across Cash/Card/bKash/Nagad, cash change calculation, atomic checkout with sequential `INV-XXXX` numbering, and 80mm customer receipt preview with logged duplicate reprints)
- Voids/Refunds, Financial Reports, CSV Export & Immutable Audit Viewer (manager-only invoice voiding, partial/full refunds with sequential `REF-XXXX` numbering and shift cash reconciliation, reconciled daily sales & payment method breakdown, top-selling items, downloadable CSV export, and searchable security/operations audit trail)
- Inventory, Purchasing & Recipe BOM Stock Deduction (suppliers directory, stock items with storage units & reorder thresholds, `PO-XXXX` Goods Received purchase bills with weighted-average unit cost, Menu Item Recipe BOM linking with Food Cost % calculation, automatic ingredient deduction when KOT rounds are sent, auto-86 Sold-Out protection when ingredients hit zero, and wastage/physical count adjustments)
- Staff & Role Management UI (create/edit Waiter, Cashier, Kitchen, Bar, Manager & Admin accounts, hierarchy-enforced role assignment, audited password rotation with immediate session revocation, and account activation/deactivation)
- Responsive touch-friendly login, floor dashboard, split-view order composer, KDS queue, cashier settlement workspace, inventory & purchasing workspace, financial reports & audit workspace, staff directory, and menu management workspace
- Feature tests for authorization, restaurant isolation, menu pricing history, order concurrency/idempotency, KOT station routing, transfers/merges, auditing, and active-order protection

See [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) for the full delivery plan.

## Local setup

### Windows / Laragon setup

Start MySQL in Laragon. The verified local server is MySQL 8.0.30 on port 3306. PHP 8.3.33, Composer 2.4.1, Node 24.13.0 and npm 11.6.2 are installed. Composer and MySQL are available through the paths below; they need not be on PATH.

For a new checkout:

```powershell
cd F:\laragon\www\restaurant-pos\backend
& 'F:\laragon\bin\composer\composer.bat' install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
& 'F:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' '--host=127.0.0.1' '--port=3306' '--user=root' --password '--execute=CREATE DATABASE IF NOT EXISTS restaurant_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan migrate --seed
php artisan pos:create-manager
```

The MySQL command prompts for the local database password (press Enter if your Laragon root password is blank). Put DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD only in ignored `backend/.env`. This installation uses `restaurant_pos`. Do not regenerate APP_KEY on an existing installation.

Seeders create zones, tables, and roles without account passwords. The manager command prompts for a hidden password. `--admin` selects Admin. Admin and Manager can manage floor/tables; Admin additionally has user/security administration permissions. No user/security administration UI is provided by the foundation.

### Start backend (terminal 1)

```powershell
cd F:\laragon\www\restaurant-pos\backend
php artisan serve --host=127.0.0.1 --port=8000
```

Seeders do not create login accounts. Run `php artisan pos:create-manager` and enter a password at the hidden prompt. Use `--admin` for an Admin account. Never put a password in source files, command arguments, or this README.

### Start frontend (terminal 2)

```powershell
cd F:\laragon\www\restaurant-pos\frontend
npm ci
npm run dev -- --host localhost --port 5173 --strictPort
```

Vite proxies `/api` to `http://127.0.0.1:8000` during development.

Open **http://localhost:5173**; backend health: **http://127.0.0.1:8000/up**. `frontend/.env.example` contains only public configuration. Session cookies are HttpOnly and SameSite=Lax; the React app keeps the CSRF token in memory and receives a fresh token at login/logout. Development CORS accepts only `http://localhost:5173` and `http://127.0.0.1:5173`, with credentials. Production requires HTTPS, APP_DEBUG=false, SESSION_SECURE_COOKIE=true, and an explicit production CORS origin.

### Exposed development account — rotation still required

On 2026-11-11, a read-only password verification confirmed that the retained SQLite `database/database.sqlite` contains an active `admin@restaurant.test` account whose hash matches the former exposed default. This is a real local database account; no evidence establishes any production use. MySQL is now the normal development database. The legacy account was not silently deleted or modified, and no Git history was rewritten. Rotate it before reusing that SQLite database, and rotate any account elsewhere where the same password was reused.

The safe rotation command preserves roles and clears server sessions and remember-me tokens:

```powershell
cd F:\laragon\www\restaurant-pos\backend
# For an account in the normal MySQL database:
php artisan pos:create-manager --reset-password
# For the retained legacy SQLite account, in a separate temporary terminal:
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE='F:\laragon\www\restaurant-pos\backend\database\database.sqlite'
php artisan pos:create-manager --reset-password
# Enter the legacy account email and your new password at the prompts; then close this terminal.
```

The `--password-env=VARIABLE_NAME` option can read a process-only environment secret for automated local setup; never pass the secret itself as an argument. Existing local `manager@local.test` is independent of the exposed SQLite account. Reset its password using the command above if you did not retain its setup password.

## Verification

```powershell
cd backend
php artisan test

cd ..\frontend
npm run build
```

SQLite in-memory is used by default for automated tests. To verify MySQL, use the dedicated disposable `restaurant_pos_test` schema, never the development schema:

```powershell
cd F:\laragon\www\restaurant-pos\backend
& 'F:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' '--host=127.0.0.1' '--user=root' --password '--execute=CREATE DATABASE IF NOT EXISTS restaurant_pos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='restaurant_pos_test'
php artisan test --compact
Remove-Item Env:DB_CONNECTION,Env:DB_DATABASE
```

Real Chrome browser acceptance test (both servers must already be running, Chrome installed):

```powershell
cd F:\laragon\www\restaurant-pos\frontend
node tests/foundation-browser.mjs
```

This creates a temporary QA manager with a memory-only random secret, verifies login → add → refresh → edit → deactivate/reactivate → logout → login again, then disables the QA account and invalidates its sessions. Clearly named QA tables remain inactive with their audit history. It also verifies missing-CSRF rejection and 401 after logout. No browser traces or credentials are saved.
