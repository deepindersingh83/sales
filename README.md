# Sales Commission & Incentive Management (ICM) SaaS

A multi-tenant sales commission / incentive compensation management web application
(SalesCookie-style). Companies design incentive plans, import sales transactions, run
auditable commission calculations, and give each rep a personal dashboard with a dispute
workflow.

Built to the specification in `docs/BUILD_PROMPT.md`. Engineering decisions and assumptions
are tracked in `DECISIONS.md`.

## Stack

- **Backend:** PHP 8.4+, Laravel 13 (see `DECISIONS.md` D2 for the version note)
- **Frontend:** Blade + Alpine.js + Tailwind CSS, Livewire for multi-step admin wizards
- **Database:** MySQL 8 in production; SQLite for local dev & CI
- **Queue:** Laravel queues (`database` driver; Redis-ready)
- **Auth:** Laravel Breeze, extended with a per-workspace role model

## Multi-tenancy

Single database, shared schema, `workspace_id` on every business table. Tenant isolation is
enforced at the ORM layer by the `BelongsToWorkspace` trait + `WorkspaceScope` global scope,
driven by the request-scoped `WorkspaceContext` singleton — never by hand-written `where`
clauses. See `app/Models/Concerns/BelongsToWorkspace.php`.

## Roles (per workspace)

| Role | Access |
|---|---|
| Full Admin | Unrestricted control over the workspace |
| Plan Admin | Full control over plans assigned to them |
| Limited Admin | Read-only; specific plans can be hidden |
| Participant | Their own dashboard only (credits, payouts, disputes) |

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan test
php artisan serve   # + `php artisan queue:work` for calculations
```

## REST API

Per-workspace bearer token (generate under **Settings → REST API**). Every call
is tenant-scoped by the token.

- `POST /api/v1/transactions` — idempotent ingest / webhook target
  (`{ "source_system": "...", "transactions": [ { "external_id", "amount", ... } ] }`).
- `GET /api/v1/payouts` — released-rewards feed for BI tools (Power BI / Tableau).

```bash
curl -X POST https://<host>/api/v1/transactions \
  -H "Authorization: Bearer wsk_..." -H "Content-Type: application/json" \
  -d '{"transactions":[{"external_id":"D-1","amount":1000,"currency":"USD"}]}'
```

## Deployment

See `DEPLOYMENT.md` for CloudPanel/LEMP steps. The most common fresh-deploy 500
is `storage/` not being writable by the PHP-FPM user (Blade can't compile views;
PHP 8.4 reports it via a `tempnam()` notice) — fix ownership/permissions on
`storage` and `bootstrap/cache`, and keep `APP_DEBUG=false` in production. A
queue worker (`php artisan queue:work`) is required for calculation runs.

## Build phasing

The MVP (product Phase 1) is delivered in build milestones — see `DECISIONS.md` and the
project board. Product Phase 2 (multi-currency FX, connectors, SSO, simulation, ASC 606,
e-signature) is out of scope; extension points are left generic.
