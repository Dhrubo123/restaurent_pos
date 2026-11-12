# Production-Ready Restaurant POS — Development Plan

Status: **Foundation implemented and browser-verified; see the dated implementation record below for verification results and remaining release blockers.**

Repository inspection (2026-11-11): the workspace is empty and is not currently a Git repository. This plan therefore assumes a greenfield React + TypeScript PWA and Laravel + MySQL implementation. The supplied demo URL was not accessible to the inspection tool; its stated table-management concept is used as direction, but its exact styling should be reviewed manually before UI implementation.

## A. Assumptions and decisions that need my input

### Recommended assumptions

1. **One restaurant location in MVP.** The schema will include a `restaurant_id`/location boundary where it prevents expensive rework, but multi-branch administration, consolidated reporting, and cross-branch menu publishing are later-phase features.
2. **Online-only transactional operation in v1.** The PWA may cache its application shell and static assets, but it will not accept or promise offline orders. If the API is unreachable, ordering and payment controls become read-only/disabled with a clear connection banner. This avoids conflicting checks, duplicate orders, stale prices, and unverified payments.
3. **Restaurant-local server is recommended for MVP** where internet reliability is uncertain: Laravel, MySQL, queue worker, real-time service, and print connector run on a small UPS-backed local machine; encrypted off-site backups and remote monitoring use the internet. Cloud hosting plus a local print connector remains a supported deployment option.
4. **Fifteen initial tables** are seeded as configurable records, divided initially between Indoor and Outdoor. Managers can create, reorder, edit, and deactivate zones/tables later without code changes.
5. **No reservations in MVP UI**, but the data model and table-status resolver support them. Reservations are a later phase unless the restaurant needs them at launch.
6. **One open order per physical table in MVP.** Another waiter opens the existing order rather than creating a second one. Order merging and table transfer are supported; splitting individual items between tables/bills is a later phase unless required for launch.
7. **Kitchen Display System (KDS) plus printer queue.** Kitchen and Bar can use touch displays for reliable status updates, while price-free KOTs are printed as operational copies. Printing is never the source of truth.
8. **Server-calculated money.** Store money in `DECIMAL(13,2)` (or integer minor units if one-currency policy is confirmed), quantities in `DECIMAL(10,3)`, and tax/service/discount rates in `DECIMAL`; never binary floating point. Currency is BDT by default.
9. **Tax and service charge are configurable.** A manager selects inclusive/exclusive tax, rate, service charge, rounding mode, and discount rules. Once an order is finalized, its calculation snapshot is immutable.
10. **Payment methods:** cash, card, bKash, Nagad, and split tender. Only gateway/provider reference numbers and masked/non-sensitive metadata are stored. Card PAN, CVV, PIN, and mobile-wallet PIN are never collected.
11. **Invoices are fiscal business documents, not tax-compliance certification.** Invoice fields and numbering will be aligned with the restaurant accountant and applicable Bangladesh requirements before launch.
12. **English-first interface**, designed so Bangla labels and receipt/KOT fonts can be added later. All timestamps are stored in UTC and shown in `Asia/Dhaka`.
13. **Reasons are mandatory** for discounts beyond configured thresholds, sent-item cancellation, order cancellation, invoice void, refund, cash adjustment, table transfer, and KOT reprint.
14. **Concurrency is optimistic with explicit conflicts.** Orders have a version number; stale updates receive HTTP 409 and the client reloads/diffs instead of silently overwriting another waiter’s work.
15. **Real-time updates use WebSockets where available**, with short polling fallback. All authoritative changes still go through the Laravel API.

### Decisions that materially affect architecture

These should be confirmed before Milestone 2:

| Decision | Recommended choice | Architectural impact |
|---|---|---|
| Hosting | Local server + off-site backups | Best LAN resilience and direct printer access; requires UPS and on-site device maintenance |
| Offline ordering | Not in v1 | True offline ordering requires a local-first database, conflict resolution, device identity, and significantly more testing |
| Reservations | Later phase | If required at launch, add calendar/guest workflows and stricter table-state precedence now |
| Fiscal/tax rules | Accountant-approved before billing build | Determines invoice fields, rounding, tax inclusion, void/refund records, and retention |
| KDS hardware | Kitchen and Bar displays plus printers | Enables preparation status; printer-only workflow cannot reliably report Preparing/Ready |
| Split scope | Split payment in MVP; split bill/items later | Item-level bill splitting adds allocation and reconciliation complexity |
| Multi-branch | Later, but tenant-aware core | Determines uniqueness constraints, reporting boundaries, and authorization scope |
| Payment integration | Manual provider reference in MVP | Direct bKash/Nagad/card integrations require gateway contracts, callbacks, reconciliation, and secrets management |

Before development, also confirm receipt width (58/80 mm), kitchen/bar printer interfaces (Ethernet preferred), table naming convention, initial menu, tax/service rules, discount limits, refund policy, shift rules, and whether Bangla printing is required.

## B. Complete feature list, separated into MVP and later phases

### MVP

**Identity and administration**

- Secure login/logout, password reset by admin, forced password change, session/device list, inactivity timeout, and account lock/rate limiting.
- Roles: Manager/Admin, Waiter, Cashier, Kitchen, Bar. Server-side permissions with optional user-specific overrides only if later proven necessary.
- User create/edit/deactivate, role assignment, password reset, and audit trail. A user with historical activity is deactivated, never deleted.
- Restaurant profile, currency, timezone, invoice/KOT prefixes, tax, service charge, discount limits, and printer routing settings.

**Floor and table management**

- Seed 15 tables; zone create/edit/reorder/deactivate; table create/edit/reorder/deactivate.
- Table name/number, capacity, zone, active flag, sort position, optional shape/display coordinates later.
- Visual floor list/grid filtered by Indoor/Outdoor/other zones.
- Derived statuses: Available, Occupied, Bill Requested, Partially Paid, Reserved (when enabled), and Inactive.
- Prevent deactivation or deletion while an open order/reservation exists.
- Table transfer with locking, permission check, reason, and audit record.
- Merge two open orders with validation, deterministic price/tax preservation, payment prohibition, reason, and full audit.

**Menu**

- Categories, items, variants/sizes, modifier groups/options, preparation station (Kitchen/Bar), availability/sold-out state, item notes, optional images, tax category, and display order.
- Time-independent availability toggle in MVP; schedules later.
- Effective-dated price history. Order lines copy names, SKU, price, tax, modifiers, and station snapshots so historical orders do not change.
- Search and category browsing optimized for touch.

**Ordering**

- Open/reopen table order, guest count, add items/variants/modifiers, quantities, item notes, and order note.
- Freely edit/delete only unsent quantities. Once sent, correction requires authorized cancellation and reason; replacement becomes a new line/round.
- Send unsent items as a round. Server atomically creates station-specific KOTs containing only newly sent quantities.
- Idempotency key for open order, send round, payment, finalize, refund, and print requests; repeated tap/retry returns the original result.
- Live preparation state per item/quantity: Pending, Preparing, Ready, Served, Cancelled.
- Optimistic order version and clear conflict resolution if two waiters edit simultaneously.

**Preparation and printing**

- Kitchen and Bar queues filtered to their stations; KOT number, table, items/quantities, modifiers/notes, waiter, and sent time; no prices.
- Pending → Preparing → Ready, with served acknowledgement by waiter/cashier where useful.
- Separate KOT for every later round and station.
- Durable print-job queue, connector heartbeat, retry/backoff, deduplication, failed-job alert, manual retry/re-route, reprint counter, actor/reason.
- Receipt/invoice printing for cashier; browser print is only a visible fallback, never silent background printing.

**Billing, payments, and shifts**

- Provisional bill generated from server totals.
- Fixed/percentage order discount within role limit, optional item discount if confirmed, service charge, tax, rounding, and reason/approval rules.
- Cash, card, bKash, Nagad, and split payments; cash-tendered/change calculation; provider reference validation without sensitive credentials.
- Partial payment leaves the order open and table occupied. Only when valid captured payments equal the final amount and checkout commits does the invoice finalize and the table become available.
- Unique sequential final invoice number allocated inside the checkout transaction; invoice snapshot is immutable.
- Receipt reprint history. Authorized full/partial refund against payments/invoice; void rules distinguish same-shift mistaken invoice from post-settlement refund and retain the original record.
- Cashier shift open/close, opening float, expected totals by payment method, counted cash, variance, notes, and manager review.

**Reporting and audit**

- Daily sales, payment-method totals, item/category sales, waiter performance, discounts, cancellations, refunds, cashier shifts, open orders, and printer failures.
- Date, shift, user, station, payment method, category filters as relevant.
- CSV export in MVP; printable/PDF summary later or MVP if operationally required.
- Append-oriented audit events capture actor, action, entity, before/after safe fields, reason, request/correlation ID, IP/device, and timestamp.

### Later phases

- Reservations, waitlist, guest contact/consent, deposits, no-show tracking, and reservation reminders.
- True offline order entry with local encrypted queue, reconciliation UI, conflict policy, offline price/menu validity window, and device provisioning.
- Multi-branch menu publishing, inventory, recipe costing, wastage, purchasing, and stock deductions.
- Item/bill splitting, seat/course assignment, tabs, delivery/takeaway, online ordering, QR ordering, and customer display.
- Direct payment gateway/terminal integrations and automated settlement reconciliation.
- Loyalty, CRM, gift cards, promotions engine, scheduled menu availability, happy hour, combos, and coupons.
- Drag-and-drop graphical floor plan, table shapes, reservations overlay, and capacity optimization.
- Advanced analytics, scheduled reports, accounting export/API, BI warehouse, and anomaly detection.
- Bangla UI/receipts, accessibility audit, additional currencies, and country-specific fiscal integrations.
- Central cloud management with local edge nodes for multiple branches.

## C. Role and permission matrix

Legend: **Y** allowed, **Own** allowed for own assigned/opened tables within policy, **Station** limited to Kitchen/Bar station, **Limit** allowed within configured threshold, **Approve** requires elevated approval, **N** denied. Every decision is enforced by Laravel policies/gates and service-layer rules; React visibility is convenience only.

| Action | Manager/Admin | Waiter | Cashier | Kitchen | Bar |
|---|---:|---:|---:|---:|---:|
| View active floor/table status | Y | Y | Y | N | N |
| Manage zones/tables | Y | N | N | N | N |
| Deactivate table | Y, only if clear | N | N | N | N |
| View menu | Y | Y | Y | Station | Station |
| Manage menu/categories/modifiers | Y | N | N | N | N |
| Change menu prices | Y + reason/history | N | N | N | N |
| Change item availability | Y | N | Optional | Station only | Station only |
| Open/reopen order | Y | Own | Y | N | N |
| Add/edit unsent items | Y | Own | Y | N | N |
| Send KOT round | Y | Own | Y | N | N |
| View KOTs | Y | Own table status | Y | Station | Station |
| Mark Preparing/Ready | Y | N | N | Kitchen only | Bar only |
| Mark Served | Y | Own | Y | N | N |
| Cancel unsent item | Y | Own | Y | N | N |
| Cancel sent KOT item | Y | Approve/permission + reason | Approve/permission + reason | Request only | Request only |
| Reprint KOT | Y + reason | N | Permission + reason | Station + reason | Station + reason |
| Transfer table | Y + reason | Permission + reason | Permission + reason | N | N |
| Merge orders | Y + reason | N | Permission + reason | N | N |
| View provisional bill | Y | Own | Y | N | N |
| Apply discount | Y | Limit (default 0–5%) | Limit (configured) | N | N |
| Override discount limit | Y + reason | N | N | N | N |
| Change tax/service settings | Y | N | N | N | N |
| Record payment/finalize invoice | Y | N by default | Open shift only | N | N |
| Reprint receipt/invoice | Y + logged | N | Y + logged | N | N |
| Void finalized invoice | Y + reason | N | Request/limited same-shift policy | N | N |
| Issue refund | Y + reason | N | Permission + manager approval/limit | N | N |
| Open/close own cashier shift | Y | N | Y | N | N |
| Approve shift variance | Y | N | N | N | N |
| View operational open-order report | Y | Own | Y | Station queue | Station queue |
| View sales/refund/financial reports | Y | N | Own shift summary | N | N |
| Export reports | Y | N | Optional own shift | N | N |
| View audit/security logs | Y | N | N | N | N |
| Manage users/roles | Y | N | N | N | N |
| Change restaurant/settings/printers | Y | N | N | N | N |

Recommended separation of duties: admins cannot erase audit/payment records; cashier refunds over a configurable amount require manager re-authentication; no user approves their own exceptional discount/refund where staffing permits. Kitchen/Bar may flag a cancellation request, but cannot financially cancel a sent item.

## D. Screen map and user journeys

### Shared visual system

- Calm, high-contrast interface with large 48–56 px minimum touch targets, 16 px+ body text, strong focus states, and status represented by text/icon plus color.
- Suggested table states: Available (green), Occupied (blue), Bill Requested (amber), Partially Paid (purple), Reserved (teal), Inactive (gray), with elapsed time and server-authoritative status.
- Persistent connectivity/print-service indicators; inline validation; non-blocking success confirmation; errors state what happened, whether data was saved, and the next safe action.
- Loading skeletons for reads and disabled buttons with progress for mutations. A mutation never appears successful until acknowledged by the server.
- Mobile: single-pane with bottom navigation. Tablet: floor/menu/order split view. Desktop: sidebar plus multi-column workspace. Kitchen/Bar: high-density queue cards and full-screen mode.

### Screen list

**Authentication/shared:** Login, change password, device/session notice, profile, connection status, access denied, error recovery.

**Manager:** Dashboard; zones/tables; floor preview; categories/items/variants/modifiers; users/roles; tax/service/discount settings; stations/printers; invoice/KOT numbering; shifts; reports; audit log; printer failures; backup/system health.

**Waiter:** Floor/tables; order composer; item customization; active order/rounds; preparation tracker; transfer request/action; bill preview; notifications for Ready/conflict/failure.

**Kitchen/Bar:** Station queue; KOT detail; preparing/ready actions; cancelled-item acknowledgement; reprint/request help; station history and printer status.

**Cashier:** Floor/open checks; bill review; discount/approval; payment/split tender; receipt/invoice; refunds/voids; shift open/close; own shift summary; failed receipt jobs.

### Core journeys

**Waiter order:** Login → select zone/table → see status and current owner/version → enter guest count → search/category-select item → choose variant/modifiers/note → review unsent tray → tap Send once → server commits round/KOTs → visible success with KOT numbers → later add a new round → follow Ready indicators → mark served/request bill.

**Concurrent waiter:** Waiter B opens the same table and sees current order/assigned waiter. Policy may allow collaboration or read-only access. If A changes it first, B’s stale mutation returns 409 with “Order changed by A at 20:14”; B reloads, preserves a recoverable local draft where safe, and explicitly reapplies it. Opening the table never creates a duplicate order because the database enforces one open table order.

**Kitchen/Bar:** New KOT arrives by real-time event and print queue → staff acknowledges/starts it → marks individual lines or whole ticket Ready → waiter receives update. If printer fails, the KDS remains authoritative and a red print-failure badge offers retry; preparation does not wait for a printer if the screen is available.

**Cashier:** Open shift → select table/open order → verify bill → apply permitted discount/approval → choose one or more tenders → server validates total and records payments → when fully paid, finalize atomically → invoice and receipt print job created → table becomes Available → close shift with count and variance.

**Manager setup:** Create zones → create/reorder 15+ tables → configure stations/printers → build categories/items/variants/modifiers/prices → create users/roles → configure financial rules → run test KOT/receipt → review audit/system status.

**Refund:** Search invoice → inspect payments/refundable balance → select full/partial lines/amount according to policy → manager permission/re-authentication → mandatory reason → server creates immutable refund and negative accounting events → print refund receipt → reports and shift totals update.

## E. Database schema

All transactional tables use bigint/UUID primary keys consistently, `created_at`, `updated_at`, and UTC timestamps. Foreign keys are enforced; financial and audit rows use restrictive deletion. Sensitive free text is length-limited and escaped on output.

### Identity and configuration

- `restaurants`: `id`, `name`, `timezone`, `currency_code`, contact/invoice fields, `active`.
- `users`: `id`, `restaurant_id`, `name`, `username/email`, `password`, `active`, `last_login_at`, `password_changed_at`; unique `(restaurant_id, username)`.
- `roles`, `permissions`, `role_user`, `permission_role` (or Spatie-compatible equivalents); permissions are stable machine keys.
- `settings`: scoped typed settings with `restaurant_id`, `key`, encrypted/JSON `value`, version; unique scope/key. Secrets preferably live outside this table in environment/secret store.
- `devices`: trusted terminal/tablet identity, label, type, last_seen, revoked_at.

### Floor and reservations

- `zones`: `id`, `restaurant_id`, `name`, `sort_order`, `active`; unique active-friendly name rule per restaurant.
- `dining_tables`: `id`, `restaurant_id`, `zone_id`, `code`, `display_name`, `capacity`, `sort_order`, `active`, optional layout JSON/version; unique `(restaurant_id, code)`, checks `capacity > 0`.
- `reservations` (later-enabled): table/guest/times/status/contact/notes; index `(dining_table_id, starts_at, ends_at, status)`; overlap is guarded transactionally because MySQL lacks a simple general exclusion constraint.
- `table_transfers`: from/to table, order, actor, reason, timestamp.
- `order_merges`: source/target order, actor, reason, merge snapshot.

Table status is **not stored as an independently editable field**. Resolver precedence: inactive → occupied/partially paid/bill requested from an open order → reserved when an active reservation is within the configured occupancy window → available. Conflicting reservation/order states generate an operational warning; existing open order wins for safety.

### Menu

- `menu_categories`: restaurant, name, station default optional, sort, active.
- `menu_items`: category, SKU, name, description, image path, tax category, station, active, available, sort.
- `item_variants`: item, name/size, SKU, active, sort.
- `modifier_groups`: name, min/max selections, required, sort, active.
- `modifier_options`: group, name, station override optional, active, sort.
- `item_modifier_groups`: item/variant to group mapping.
- `price_history`: priceable type/id (variant/item/modifier), `amount DECIMAL(13,2)`, effective_from/to, changed_by, reason; index `(priceable_type, priceable_id, effective_from)`, prevent overlapping effective periods in service transaction.
- `tax_categories`: name, rate, inclusive flag, effective dates.

### Orders and KOTs

- `orders`: restaurant, table, order number, status (`OPEN`, `BILL_REQUESTED`, `PARTIALLY_PAID`, `PAID`, `CANCELLED`, `MERGED`), waiter, guest_count, `version`, opened/closed timestamps, subtotal/discount/service/tax/rounding/total/balance decimals, financial-rules snapshot JSON; unique order number.
- Enforce one open-like order per table using a guarded transaction plus a lock row and/or nullable `open_table_id` uniqueness strategy; never rely only on an application pre-check.
- `order_items`: order, source menu/variant IDs nullable for history, snapshot names/SKU/station/unit price/tax, ordered/sent/cancelled/served quantities, note, status, line totals, version.
- `order_item_modifiers`: order item, source option nullable, snapshot name/price/tax/quantity.
- `order_rounds`: order, round number, created/sent by, idempotency key, sent_at; unique `(order_id, round_number)` and request key.
- `kots`: restaurant, order, round, station, ticket number, status, created/sent times; unique station ticket number and unique `(round_id, station)`.
- `kot_items`: KOT, order item, sent quantity, snapshot note/modifiers, status timestamps, cancelled quantity/reason/by.
- `kot_status_events`: KOT/item, from/to status, actor, timestamp, note; indexed by KOT/time.

### Billing, payments, shifts

- `discounts`: order/item target, type, rate/amount, computed amount, reason, requested_by, approved_by, created_at.
- `cashier_shifts`: cashier, terminal, opened/closed, opening float, expected cash, counted cash, variance, status, approved_by, notes.
- `payments`: order, shift, method, amount, status (`PENDING`, `CAPTURED`, `VOIDED`, `REFUNDED`, `FAILED`), provider reference, external transaction reference, idempotency key, received_by, captured_at; unique request key and appropriate provider reference uniqueness.
- `payment_allocations`: payment to invoice/order/refund allocation if split and partial workflows require explicit accounting.
- `invoices`: order, sequential invoice number, immutable customer/order/totals/tax/payment snapshot, issued_by/at, status (`ISSUED`, `VOIDED`, `PARTIALLY_REFUNDED`, `REFUNDED`); unique `(restaurant_id, invoice_number)`.
- `invoice_items`: immutable line snapshots and financial breakdown.
- `refunds`: invoice/payment, refund number, amount, method, reason, requested/approved/processed actors, status, provider reference, idempotency key.
- `refund_items`: refund-to-invoice-line allocations.
- `invoice_sequences`, `kot_sequences`, `order_sequences`: restaurant/date or configured period, next value; row-locked allocation.

### Printing, audit, operations

- `printers`: restaurant, station/type, name, connector, network alias (not exposed broadly), paper width, active, capabilities.
- `print_jobs`: type, source type/id, printer, payload/template version, content hash/dedupe key, status (`QUEUED`, `CLAIMED`, `PRINTED`, `FAILED`, `CANCELLED`), attempts, next_attempt_at, error code/safe message, created/printed times; unique dedupe key for original print.
- `print_attempts`: job, connector/device, attempted_at, result, safe error, duration.
- `print_reprints`: original job, new job, actor, reason.
- `connectors`: identity, secret hash/certificate identity, version, heartbeat, last IP, active.
- `audit_events`: restaurant, actor nullable/system, action, auditable type/id, before/after safe JSON, reason, request ID, IP, user agent/device, timestamp; indexes `(auditable_type, auditable_id, created_at)` and `(actor_id, created_at)`.
- `idempotency_keys`: restaurant/user/device, endpoint scope, key, request hash, response code/body reference, resource, expiry; unique scope/key.
- `outbox_events`: aggregate/type, payload, published_at, attempts; written in the same transaction as domain changes.
- `security_events`: login failures, lockouts, permission denials, suspicious activity; retention/access controlled.

High-volume indexes include order table/status, orders opened/closed time, KOT station/status/time, payments shift/method/status/time, invoice issued time, print job status/next attempt, and all report date/filter columns. Use `CHECK` constraints for nonnegative quantities/amounts where supported and service validation everywhere.

## F. API and backend business rules

API prefix `/api/v1`; JSON; authenticated endpoints use secure first-party session cookies with Laravel Sanctum for same-origin deployment, or short-lived tokens with rotation for a separately hosted trusted PWA. Use CSRF protection for cookie authentication. Pagination, filtering, stable error codes, request/correlation IDs, and OpenAPI documentation are required.

### Endpoint outline and access

**Auth/shared**

- `POST /auth/login`, `POST /auth/logout`, `GET /me`, `PUT /me/password`, `GET/DELETE /me/sessions/{id}` — authenticated user/self; login rate-limited.
- `GET /bootstrap` — authenticated; role-filtered configuration, permissions, zones, stations, feature flags.

**Users/settings (Manager)**

- `GET/POST /users`, `GET/PATCH /users/{id}`, `POST /users/{id}/deactivate`, `POST /users/{id}/reset-password`.
- `GET/PATCH /settings`, `GET /audit-events`, `GET /system/health-summary`.

**Zones/tables**

- `GET /zones`, `GET /tables`, `GET /floor` — Manager/Waiter/Cashier, role-filtered.
- `POST/PATCH /zones`, `POST /zones/{id}/deactivate`, reorder endpoints — Manager.
- `POST/PATCH /tables`, `POST /tables/{id}/deactivate`, reorder endpoints — Manager.
- `POST /orders/{id}/transfer`, `POST /orders/{id}/merge` — Manager or explicit operational permission, with reason.

**Menu**

- `GET /menu` — operational roles; only current available/active data by default.
- CRUD `/menu/categories`, `/menu/items`, `/menu/variants`, `/menu/modifier-groups`, `/menu/prices` — Manager; price write requires reason and creates history.
- `POST /menu/items/{id}/availability` — Manager or station-scoped permission.

**Orders/KOTs**

- `POST /tables/{table}/orders` — Waiter/Cashier/Manager; idempotent and returns existing open order when policy permits.
- `GET /orders/{id}`, `PATCH /orders/{id}`, `POST /orders/{id}/items`, `PATCH/DELETE /orders/{id}/items/{line}` — authorized operational users; version required.
- `POST /orders/{id}/send` — authorized; idempotency key and expected version required.
- `POST /orders/{id}/request-bill`, `POST /orders/{id}/cancel` — permission/reason rules.
- `GET /kots?station=`, `GET /kots/{id}`, `POST /kots/{id}/start`, `POST /kots/{id}/ready`, `POST /kot-items/{id}/cancel` — station/manager scoped.
- `POST /kot-items/{id}/served` — Waiter/Cashier/Manager.

**Billing/payments**

- `GET /orders/{id}/bill-preview` — Waiter-own/Cashier/Manager; always recalculated server-side.
- `POST /orders/{id}/discounts`, `DELETE /orders/{id}/discounts/{id}` — threshold and approval policy.
- `POST /shifts/open`, `GET /shifts/current`, `POST /shifts/{id}/close`, `POST /shifts/{id}/approve`.
- `POST /orders/{id}/payments` — Cashier/Manager with open shift, idempotency key.
- `POST /orders/{id}/checkout` — Cashier/Manager; idempotent; may combine final captured tender and checkout if desired.
- `GET /invoices/{id}`, `POST /invoices/{id}/reprint`, `POST /invoices/{id}/void`, `POST /invoices/{id}/refunds`.

**Printing/reports**

- `GET /print-jobs`, `POST /print-jobs/{id}/retry`, `POST /print-jobs/{id}/reprint`, `GET /printers/status` — role-filtered.
- Connector-only `POST /connectors/heartbeat`, `POST /connectors/jobs/claim`, `POST /connectors/jobs/{id}/result` using separate scoped credentials and claim leases.
- `GET /reports/{report-key}`, `GET /reports/{report-key}/export` — Manager; limited own-shift endpoint for Cashier.

### State machines

- **Order:** `OPEN → BILL_REQUESTED → PARTIALLY_PAID → PAID`; `OPEN/BILL_REQUESTED → CANCELLED` only when permitted; source of a merge → `MERGED`. Adding items after bill request resets to OPEN after confirmation. No mutation after PAID except refund/void documents.
- **Order item/KOT:** unsent draft → Pending when sent → Preparing → Ready → Served. Pending/Preparing may become Cancelled only through authorized cancellation, with station notification and immutable event. Ready cancellation needs stronger approval.
- **Payment:** Pending → Captured or Failed; Captured → Voided (only policy window/no settlement) or Partially/Fully Refunded through refund records.
- **Invoice:** Issued → Voided only under strict void policy, or Partially Refunded → Refunded. Never delete/reuse invoice numbers.
- **Print:** Queued → Claimed → Printed; claim timeout returns to Queued; repeated failures → Failed; manual retry creates another attempt on same original job; reprint creates linked job labeled REPRINT.

### Transaction and locking rules

- Opening an order locks the table/open-order guard and atomically verifies active table and absence/presence of an existing open order.
- Sending a round locks the order and draft lines, verifies expected version, recalculates snapshots, creates one round, station KOTs/KOT lines, print jobs, audit and outbox events, and increments version in one DB transaction.
- Checkout locks order, invoice sequence, captured payments, and table guard; recalculates totals from immutable lines and current allowed adjustments; verifies captured total exactly equals amount due; allocates invoice number, creates immutable invoice/items, marks order paid, releases table, queues receipt, and records audit/outbox in one transaction.
- External payment calls, printer I/O, WebSocket delivery, and email/export work occur outside database transactions through queued jobs/outbox. Never hold DB locks during network calls.
- Refund locks invoice/refundable allocations, enforces maximum refundable balance, writes refund/audit/outbox atomically, then processes external provider action with a recoverable state if applicable.

### Idempotency and duplicate prevention

Clients send a UUID `Idempotency-Key` for create/send/pay/checkout/refund/print actions plus an expected order version where relevant. The server stores request hash and result under user/device/endpoint scope. Same key and same body returns the original response; same key with a different body returns 409. Database unique constraints are the final defense. Buttons disable while pending, but frontend behavior is not relied upon.

### Calculation and validation rules

- The API accepts item/variant/modifier IDs and quantities—not authoritative prices or totals. It resolves active menu configuration, copies snapshots, and calculates with a documented decimal rounding policy.
- Discounts are applied in a defined order (recommended: item discounts, order discount allocation, service charge, tax according to inclusive/exclusive setting, final rounding). The accountant must approve the sequence.
- Table ownership, restaurant scope, active state, station, shift, permissions, and object-level access are checked on every action.
- Error envelope includes stable `code`, user-safe `message`, field errors, retryability, and correlation ID; logs contain diagnostics but not secrets or sensitive payment data.

### Real-time and background work

Laravel Reverb/Pusher-compatible WebSockets publish after commit from the outbox: floor status, order version, new KOT, KOT status, Ready alert, print status, and availability changes. Authorize private restaurant/station/user channels. Poll every 10–30 seconds if sockets fail.

Queue workers handle print dispatch, export generation, notifications, image processing, reconciliation, monitoring alerts, retention, and backup checks. Use Redis for queues/cache/locks if operationally supported; database queue is acceptable only for a small initial local deployment after load testing. Failed jobs have alerts and an operator retry path.

## G. KOT and printer architecture

### KOT rules

- Each send action creates one `order_round`. Lines are grouped by snapshot preparation station.
- If a round contains food and drinks, it creates two sequential station tickets: Kitchen and Bar. A later round always creates new ticket(s) containing only the newly sent quantities.
- KOT includes restaurant name optional, large ticket number, table, round, items, quantities, variants/modifiers, item notes, waiter, sent timestamp, and optional guest count. It contains no prices or payment details.
- Cancelling a sent item creates a clearly marked cancellation ticket/event routed to the same station; it does not edit or erase the original KOT.
- Reprints visibly say `REPRINT`, include original ticket number and reprint time, and are logged with actor/reason.

### Recommended hardware path

Waiter tablets and cashier browsers call the API over secure Wi-Fi/LAN. They do **not** connect directly to arbitrary printers. Laravel creates durable print jobs. A local print connector service, running on the restaurant server or a dedicated always-on mini PC, authenticates to the API, claims jobs, renders a versioned ESC/POS or vendor payload, sends it to an Ethernet/OS-queued printer, and reports the result.

Prefer wired Ethernet thermal printers with static DHCP reservations, supported code pages/fonts, status reporting, and UPS-backed switches/server. USB printers may connect to the print host but create a single physical dependency. Avoid Bluetooth for fixed Kitchen/Bar devices. Use separate Kitchen, Bar, and Cashier printers; optionally configure a fallback printer.

### Deployment comparison

| Model | Strengths | Risks/requirements |
|---|---|---|
| Local restaurant server (recommended MVP) | Ordering and KDS continue during internet outage; low LAN latency; simple printer connector | Server/DB/queue must be maintained, UPS-backed, monitored, and backed up off-site; local hardware failure affects site |
| Cloud API + local connector | Central updates/backups, easier remote access and multi-branch future | Internet outage stops v1 ordering/payment; connector needs outbound TLS, secure credentials, heartbeat, and queued cloud jobs; WAN latency/reliability matter |
| Cloud + local edge service (later) | Best central management and local continuity | Most complex: sync, ownership, conflict resolution, version compatibility, and operations |

### Queue, retries, and duplicates

- Original print job dedupe key is based on document type/source/printer/template version. A retry cannot create a second logical original job.
- Connector atomically claims a job with a short lease. If it crashes, the lease expires and another attempt may claim it.
- Network write success does not always prove paper output. Where printers cannot acknowledge completion, surface “sent to printer” rather than making a false guarantee; staff can reprint deliberately.
- Automatic retries use bounded exponential backoff (for example 5s, 15s, 45s, 2m, 5m) and stop in visible Failed state. Paper-out/offline errors show immediately if supported.
- Dashboard shows connector heartbeat, printer status, queue age, attempt count, last safe error, retry/re-route actions, and persistent alerts. KDS remains usable during printer failure.
- Browser printing is only a manually initiated fallback preview; browsers cannot be assumed to silently select thermal printers.

## H. Security and reliability checklist

### Security

- [ ] Threat model and OWASP ASVS Level 2-aligned checklist before release; document any exceptions.
- [ ] Argon2id password hashing with strong framework defaults; password reset tokens expire and are single-use.
- [ ] Secure, HttpOnly, SameSite cookies; HTTPS-only; session rotation at login/privilege change; inactivity and absolute expiry; remote logout.
- [ ] CSRF tokens for cookie-authenticated mutations; strict CORS allowlist if origins differ.
- [ ] Laravel policies/gates plus service-layer object checks for role, restaurant, table/order ownership, station, and shift.
- [ ] Form Request/schema validation, allowlists, bounded strings/quantities, safe file upload validation, output escaping, parameterized ORM queries.
- [ ] Rate limits for login, password reset, exports, and sensitive mutations; lockout/alert strategy that resists account enumeration.
- [ ] Re-authentication/step-up approval for refunds, large discounts, user/role changes, and security settings.
- [ ] Server resolves prices and recalculates totals/tax/discount/balance/table state. Ignore/reject frontend authority claims.
- [ ] Secrets only in environment/secret manager, never in React bundles, Git, logs, API errors, or backups without encryption.
- [ ] HTTPS internally where practical; modern TLS externally; HSTS for public deployment; secure headers and CSP.
- [ ] No PAN/CVV/PIN/mobile-wallet PIN storage. Provider reference and masked metadata only; use certified providers/terminals.
- [ ] Audit important actions and permission denials; protect logs from ordinary admins modifying/deleting them; define retention.
- [ ] Dependency scanning, pinned lockfiles, monthly patch cadence, emergency critical-patch process, and SBOM/release inventory.
- [ ] Database least-privilege account, separate backup credentials, restricted connector credentials, encrypted backups, key rotation.
- [ ] API errors disclose no stack traces, SQL, secrets, internal paths, or whether an account exists.
- [ ] Tests for anonymous access, every cross-role action, cross-restaurant/object access, ID enumeration, CSRF, rate limits, tampered prices/totals, duplicate send/checkout/refund, and stale versions.

### Reliability and failure behavior

- **Wi-Fi/LAN down on a tablet:** show offline banner and last successfully loaded data as read-only; retain only a clearly labeled local unsent UI draft where safe, but do not claim it is an order. Retry requires revalidation against current order version.
- **Internet down with local server:** core ordering/KDS/payment recording and local printing continue; cloud backups/remote monitoring queue until restored. External gateways may be unavailable and must not be marked paid without confirmed policy.
- **Internet down with cloud-only hosting:** v1 transactional actions stop. Show explicit outage state; staff follow a documented manual contingency procedure and reconcile later under manager control.
- **Application/DB server down:** health monitor alerts; clients show unavailable, not empty tables. Restart services automatically. Do not allow local fake success.
- **Printer down:** order/KOT remains committed and visible on KDS; job retries then fails visibly; staff retry/re-route/reprint with audit.
- **WebSocket down:** fall back to polling; show delayed-live indicator.
- **Duplicate network retry:** idempotency returns the original operation; unique constraints prevent double order/KOT/invoice/payment.
- **Worker down:** health alert on queue age and failed heartbeat; transactional outbox preserves events until workers recover.
- [ ] Automated encrypted daily full backup plus frequent binlog/incremental recovery appropriate to RPO; off-site copy and retention policy.
- [ ] Quarterly restore drill to an isolated environment with measured RPO/RTO and signed result. A backup is not accepted until restored successfully.
- [ ] Monitoring for HTTP errors/latency, DB capacity/replication if used, disk, queue age, connector heartbeat, printer failures, backup freshness, certificate expiry, and security events.
- [ ] Structured logs with correlation IDs and redaction; central/off-device copy where possible.
- [ ] Staging environment with sanitized/synthetic data and representative printers or simulator.
- [ ] Versioned migrations with backward-compatible expand/migrate/contract process; database backup before risky releases; documented rollback.

## I. Development milestones in dependency order

Each milestone ends with automated tests, updated OpenAPI/decision records, security review items, and a stakeholder demo. No milestone begins production use by itself.

1. **Discovery and decisions:** confirm material decisions in A; manually review reference demo; document tax/invoice/refund rules; inventory network/hardware; define service levels, RPO/RTO, and acceptance owners.
2. **Foundation:** initialize Git; Laravel/React TypeScript monorepo or clearly separated apps; Docker/dev setup; MySQL/Redis; CI; lint/type/test; environment templates; secret handling; logging/correlation; API error contract; OpenAPI; architecture decision records.
3. **Identity and authorization:** users/roles/permissions, secure session auth, policies, seed roles, user administration, security events, cross-role test suite.
4. **Restaurant setup:** settings, zones, 15 seeded tables, manager CRUD/reorder/deactivate, floor status resolver, responsive floor UI, concurrency guard.
5. **Menu and pricing:** categories/items/variants/modifiers/stations/availability, price history/snapshots, images if retained, touch menu/search, validation and authorization.
6. **Order core:** open/reopen order, guest count, draft lines/modifiers/notes, optimistic versioning, table ownership/collaboration, transfer/merge, idempotency foundation, audit/outbox.
7. **KOT and preparation:** atomic rounds, station split, ticket sequences, Kitchen/Bar KDS, status transitions, cancellation workflow, real-time events with polling fallback.
8. **Print subsystem:** connector protocol/service, printer setup, templates, durable queue/claim/retry/dedupe, KOT/receipt printing, dashboard/alerts, failure simulations.
9. **Billing and shifts:** server calculation engine, tax/service/discount approvals, bill preview, shifts, tender/split payments, partial-payment rules, atomic checkout/invoice, receipt.
10. **Void/refund and reporting:** immutable financial corrections, reprint, required reports/CSV, audit UI, shift reconciliation, permissions.
11. **Hardening:** full concurrency/idempotency suite, OWASP ASVS review, load/endurance tests, accessibility/touch tests, backup/restore drill, outage/printer chaos tests, observability/alerts, dependency review.
12. **Pilot and production:** staging UAT with restaurant staff and real hardware; menu/user import; training and runbooks; limited live pilot; reconcile pilot day; fix issues; controlled rollout; post-launch monitoring and review.

Recommended test pyramid: unit tests for money/state/permission rules; feature/API tests with real MySQL semantics; contract tests for connector/events; React component tests; Playwright role journeys; hardware-in-loop smoke tests; load/concurrency tests for opening/sending/checkout.

## J. Acceptance tests using realistic restaurant scenarios

1. **Morning setup:** Manager creates “Rooftop,” adds T16, moves it after Outdoor, edits capacity, and sees it immediately on waiter tablets. Attempting to reuse table code fails clearly.
2. **Safe deactivation:** Manager can deactivate empty T15; trying to deactivate T4 with an open order returns 409 and identifies the active order without changing the table.
3. **Price snapshot:** A burger is ordered at ৳450. Manager changes it to ৳480. The existing line/invoice remains ৳450; a newly added burger is ৳480; history shows actor/time/reason.
4. **Mixed station round:** Waiter sends two burgers, one cola, modifiers, and notes. Exactly one Kitchen KOT and one Bar KOT are created, price-free, correctly numbered, with only station-relevant lines.
5. **Later round:** Ten minutes later the waiter adds tea. A new Bar KOT is created containing only tea; earlier items are not reprinted.
6. **Double tap/retry:** Send is tapped twice and the HTTP response is lost/retried with the same key. Exactly one round, KOT per station, and original print job exists; both successful responses identify the same resources.
7. **Changed retry body:** Reusing an idempotency key with a different item set returns 409 and creates nothing new.
8. **Concurrent waiters:** Two waiters open T5 at version 7. A sends a line, making version 8. B’s version-7 edit is rejected with a useful conflict and no lost or duplicated item.
9. **Unauthorized price tampering:** Waiter posts an item with client price ৳1. Server ignores/rejects it and charges the effective server price; security/audit telemetry records suspicious input as configured.
10. **Sent cancellation:** Waiter cannot delete a sent steak. Authorized manager cancels it with a reason; Kitchen receives a cancellation notice; original KOT/history remains; bill adjusts under approved policy.
11. **Printer outage:** Kitchen printer is powered off. Order/KOT commits, appears on KDS, print job retries and becomes visibly Failed, staff prepare from KDS, manager re-routes/reprints after restoration, and all attempts are logged.
12. **Preparation flow:** Kitchen user cannot view financial totals and can only transition Kitchen lines Pending → Preparing → Ready. Bar user cannot change Kitchen KOTs.
13. **Table transfer:** Authorized user transfers open order from T3 to empty T8 with reason; all lines/KOT history remain tied to the order, T3 becomes available, T8 occupied, and audit records both tables.
14. **Invalid transfer/merge:** Transfer to an occupied table is rejected unless explicit merge is selected and permitted. Merge with captured partial payment is rejected under MVP policy.
15. **Split payment:** ৳2,350 bill is paid ৳1,000 cash and ৳1,350 bKash. Before the second captured payment, table remains occupied/partially paid. After exact full payment and checkout, one invoice issues and table becomes available.
16. **Cash change:** For ৳850 cash bill with ৳1,000 tendered, server records ৳850 payment and shows ৳150 change; tendered/change appear on receipt but do not inflate sales.
17. **Under/over payment:** Checkout with insufficient captured funds is rejected and leaves order unchanged. Unexplained overpayment is rejected unless a documented cash-tender/change path applies.
18. **Duplicate checkout:** Two cashier requests race. Row locks/idempotency/unique invoice constraints result in one invoice, one table release, and one original receipt job.
19. **Discount limit:** Waiter within configured 5% may discount with reason; 10% requires manager approval. Forging approval/role in JSON returns 403.
20. **Refund:** Authorized manager partially refunds one line. Refund cannot exceed captured/refundable balance, has its own number/reason, updates invoice/report/shift accounting, and preserves original invoice.
21. **Void control:** Cashier without permission cannot void. Manager void produces a retained voided invoice and audit entry; number is never reused.
22. **Shift close:** Cashier sees expected totals by method, enters counted cash, and records variance. Cashier cannot approve own variance; manager can review/approve.
23. **Reports reconcile:** Daily sales equals issued invoices less valid void/refund treatment under accounting rules; tender totals reconcile to captured payments; open orders are excluded from finalized sales.
24. **Role isolation:** Automated matrix verifies each denied action returns 403 even when called directly, including Kitchen refund, Waiter user management, Cashier price change, and Bar Kitchen transition.
25. **Object isolation:** Changing IDs cannot access another restaurant, another restricted station, or unauthorized order/table; response does not leak sensitive existence/details.
26. **Network loss:** Tablet loses Wi-Fi before Send acknowledgement. UI never invents success; after reconnect it queries by idempotency key/order version and shows whether the round committed.
27. **Server restart:** Restart after DB commit but before event dispatch. Outbox later sends KOT/print events exactly once logically; no transaction is lost.
28. **Backup recovery:** Restore latest approved backup plus recovery logs to isolated hardware; verify users, open orders, invoices, payments, KOTs, audit and sequences; record achieved RPO/RTO.
29. **Usability:** A newly trained waiter opens a table, adds customized items, and sends a round in minimal taps without help on a target tablet; all primary targets meet size/readability criteria.
30. **Load:** Representative peak concurrent tablets/KDS/cashiers meet agreed p95 response targets with no duplicate resources, deadlocks left unresolved, or missed updates.

## K. Deployment and hardware requirements

### Recommended production topology

- One business-grade mini PC/server (modern 4+ cores, 16 GB RAM, mirrored SSD where possible) running a supported Linux LTS, Nginx, PHP-FPM, Laravel queue/scheduler, MySQL, Redis, Reverb, and the print connector. Windows service deployment is possible but Linux is preferred for predictable server operations.
- UPS for server, router, core switch, access points, and ideally printers; automatic safe shutdown and power-restoration startup.
- Business router/firewall, managed gigabit switch, separate staff/POS VLAN and guest Wi-Fi, 5 GHz business access points sized after a site survey, strong WPA2/WPA3 credentials, no public inbound ports for local deployment, VPN for administration.
- Static DHCP reservations for server and Ethernet printers; NTP/time sync; DNS/local hostname strategy; documented network map.
- 15-table workflow typically needs waiter tablets based on staffing, one cashier computer/touch terminal, Kitchen display, Bar display, 80 mm Ethernet thermal printers for Kitchen/Bar/cashier (heat/oil-resistant kitchen model as needed), cash drawer connected through receipt printer if supported, and optional barcode/QR scanner.
- Spare paper, one tested fallback printer or swap plan, spare charger/tablet, and labeled cabling.

### Environments and release process

1. Separate development, staging, and production configuration/databases/credentials. Never copy live sensitive data to development; sanitize any production-derived staging data.
2. CI runs formatting, static analysis (PHPStan/Larastan), TypeScript checks, unit/feature/component/E2E tests, dependency/security scans, migration checks, and production builds.
3. Build immutable versioned artifacts; create release notes and database compatibility/rollback plan.
4. Before deployment: confirm fresh successful backup and restore health, queue state, disk space, connector compatibility, migration duration, staff window, and rollback owner.
5. Deploy backward-compatible code, enter narrowly scoped maintenance mode only if necessary, run migrations once, restart workers safely, warm caches, and run smoke tests for login/floor/order/KOT/print/bill without corrupting live totals.
6. Monitor errors, latency, queues, print failures, and reconciliations closely; roll back application version if criteria fail. Roll-forward database migrations are preferred; destructive migrations occur only after old code is retired and verified.
7. Pilot on limited tables/one shift, retain a documented manual outage procedure, train each role, and keep escalation contacts/runbooks at the restaurant.

### Operational targets to agree

- Target local p95 reads under 300 ms and mutations under 600 ms excluding external payments/printing, measured under peak load.
- Availability target and support hours; suggested initial RPO ≤ 15 minutes and RTO ≤ 2 hours, subject to cost and business tolerance.
- Daily automated backup, frequent recovery logs, off-site encrypted copies, documented retention, quarterly restore test.
- Monthly planned updates plus expedited critical security patches; staging soak and rollback on every production release.
- Daily manager checks: failed print jobs, open/aged orders, shift variances, backup status. Weekly checks: user access, storage, connector/printer health. Monthly checks: dependency/security patches and audit sampling.

### Review gate

Coding should start only after the material decisions in Section A are confirmed and this plan is approved. The first implementation deliverable should be Milestones 1–3, not the complete product at once.

## Foundation implementation record — 2026-11-11 (Asia/Dhaka)

Scope of this follow-up: credential removal, cookie-session/CSRF correctness, safe API errors, real-browser verification, and local setup documentation. Newer menu/order/KOT work already present in the shared workspace was preserved; it was not implemented in this foundation pass.

### Decisions and delivered changes

- Normal development uses Laragon MySQL 8.0.30 (`restaurant_pos`). The existing SQLite database is retained for recovery/reference; isolated automated tests default to SQLite in-memory. MySQL tests use only `restaurant_pos_test`.
- Session cookies and the existing web-middleware API remain the authentication mechanism. Login now returns the newly rotated CSRF token and React immediately replaces its in-memory token. Logout invalidates the session and refreshes the token; no authentication secret is stored in localStorage.
- Development CORS explicitly permits localhost:5173 and 127.0.0.1:5173 with credentials. SameSite=Lax/HttpOnly session cookies are retained. HTTPS and secure cookies are required for production.
- API validation, 401, 403, 404, 409, 419, 429 and unexpected-error responses use safe JSON. Unexpected errors do not expose exception text, traces or credentials even with debug enabled. Rate-limit responses retain Retry-After. Missing Accept headers no longer cause an authentication redirect failure/500.
- Admin and Manager are distinct roles. Both manage restaurant operations/floor; Admin additionally owns manage-users and manage-security. These future capabilities are permission definitions, not a claim that user/security administration screens are complete.
- Source and README no longer contain the former default login password. Seeders create no password-bearing account. `pos:create-manager` accepts a hidden password prompt or a process environment variable. `--reset-password` rotates an existing password, preserves roles, clears remember-me and deletes that user's server sessions.
- Manager floor supports zone/table add/edit and activation/deactivation, unique names/codes, capacity validation, and occupied-table protection. Heading contrast was corrected in the live UI.

### Evidence and actual verification

- MySQL development migrations/seeders were previously run successfully. Current migration status was checked; all listed migrations are applied.
- Foundation tests on SQLite: **25 passed, 100 assertions**.
- The same foundation tests on Laragon MySQL 8.0.30: **25 passed, 100 assertions**.
- Full current workspace suite before the final rotation test: SQLite **37 passed, 229 assertions**; MySQL **36 passed, 1 failed**. The failure is in newer `KotPreparationTest`: strict associative-array key-order equality on replayed MySQL JSON. This lies outside foundation scope and remains a release blocker for that subsystem.
- Real installed Chrome tested through Playwright: login, create uniquely named QA table, reload and persistence, edit name/capacity, deactivate/reactivate, logout, protected request returns 401, login again with fresh CSRF, final logout. **Passed.** A real POST without a CSRF token returned 419. A floor screenshot was visually inspected.
- Temporary QA users were disabled with randomized passwords and sessions removed after each browser run. QA tables remain clearly named and inactive, with their audit history intact.
- Desktop browser automation could not initialize due to the Windows sandbox helper error; Playwright executed the real browser flow instead. This is automated browser verification, not a claim of a human manual check.
- PHP formatting was applied to files changed in this pass.
- `npm run build` failed in pre-existing/newer order integration: `OrderWorkspace` requires `canCancelSentItems`, `onCancelSentItem`, `onSendRound`, and `onViewOrderKot`, which its caller does not supply. Foundation runs in the Vite browser, but the current combined production build cannot be certified until this unrelated integration is completed.

### Security exposure and remaining risks

A read-only hash check confirmed that retained SQLite has an active `admin@restaurant.test` account accepting the former exposed password. This proves local account use, not production use. Its password still requires user-chosen rotation before that database is reused. The normal MySQL account is separate. README provides the hidden-prompt rotation commands. No Git history was rewritten; the repository had no tracked files/commits during this audit.

Source cleanup does not rotate credentials on other copies or deployments. Rotate any reused credential there as well. This Laragon instance uses a local root database connection; create a dedicated least-privilege database account before deployment. The broader production security/backup/restore milestones remain outstanding.

### Next milestone / release gate

Foundation browser and database checks are complete, but the combined project is not production-ready. Resolve the order-screen production build mismatch and the MySQL KOT test failure within their own ongoing milestones, and complete the legacy account rotation as needed. Menu/pricing is the planned dependency before waiter ordering; existing newer work requires its own review and acceptance rather than being counted as part of foundation completion.

## Menu configuration and tablet ordering implementation record — 2026-11-12 (Asia/Dhaka)

### Scope completed

- Added a backward-compatible `menu_subcategories` table and nullable `menu_subcategory_id` on menu items. Existing categories and items remain valid: items without a subcategory continue to appear directly inside their category.
- Added active/inactive and display-order support for subcategories. A subcategory cannot be deactivated while it contains active items, and it cannot be activated until its parent category is active.
- Completed manager/admin add-on configuration: modifier groups now support required/optional selection, min/max choice rules, display order, active state, editable choices, price adjustments, and per-choice availability. Invalid rules (including min greater than max, required with zero minimum, or too few available choices) are rejected server-side.
- Completed menu item configuration in the manager UI: category, optional subcategory, tax category, station, availability, display order, variants, and assigned add-on groups are edited together.
- Defined and implemented the variant pricing rule: **an available selected variant replaces the base item price**. The base price applies only to items with no available variants. Variant and modifier availability is enforced by Laravel.
- Expanded the tablet ordering UI to include subcategory items, unavailable variant/choice feedback, client-side max-choice guidance, required-choice feedback, large controls, search/category filtering, and server-authoritative order updates.
- Laravel remains authoritative for all money. Order lines snapshot item/variant names, station, unit price, tax rule, selected modifier names and prices, quantities, and notes. Later menu changes do not alter existing order lines. KOTs continue to use those snapshots while excluding prices.
- Existing optimistic order version checks, idempotent send-round behavior, CSRF protection, secure cookie sessions, role gates, and Kitchen/Bar station splitting were retained. The MySQL-only idempotency test was corrected to compare logical JSON content rather than non-semantic object key order.
- Tablet bottom navigation now includes a visible, accessible sign-out action; the earlier desktop-only sign-out control was inaccessible at tablet width.

### Verification actually run

- Laragon MySQL migration `2026_11_12_000007_add_subcategories_and_menu_configuration` applied successfully. A first attempt created an empty partial new table then failed because MySQL index identifiers are limited to 64 characters; the verified empty table was removed and the migration corrected with an explicit short index name before rerunning. Existing application data was not modified.
- Focused menu/order security tests: **3 passed, 27 assertions**.
- Full backend suite on SQLite: **42 passed, 266 assertions**.
- Full backend suite on isolated Laragon MySQL database `restaurant_pos_test`: **42 passed, 266 assertions**.
- Frontend lint and production build: **passed**.
- Real Chrome automated smoke test at a 900 × 1100 tablet viewport: login, table creation/persistence/edit/deactivate/reactivate, CSRF rejection, logout, post-logout 401, fresh-CSRF login, and final logout: **passed**. It uses a temporary random-password QA account, then disables that account and removes its sessions.

### Remaining release risks / next milestone

- Physical printing was not implemented or verified. The 80 mm browser print preview is a user-initiated visual fallback only; it is not evidence of printer delivery. The print connector/queue milestone remains separate.
- A human service-shift acceptance run should still exercise the exact menu configuration → customized tablet order → KOT workflow with restaurant staff and target hardware. Automated feature tests cover the API rules and snapshot/KOT behavior; the real-Chrome tablet smoke test covers session/floor responsiveness.
- Payments, invoices, inventory, and fiscal/tax policy remain intentionally out of scope for this milestone.
