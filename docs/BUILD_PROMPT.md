# Build Prompt: Sales Commission & Incentive Management SaaS (SalesCookie-style)

> Paste this whole document into Claude Code as your starting instruction. It's written to be self-contained — Claude Code should be able to scaffold the full MVP from this alone, asking clarifying questions only where explicitly flagged.

---

## 1. Project Summary

Build a multi-tenant **sales commission / incentive compensation management (ICM)** web application, modeled functionally on SalesCookie (salescookie.com). The product lets a company:

1. Design incentive/commission plans (quotas, tiers, formulas, rewards)
2. Import sales transactions (CSV now, API/webhook later)
3. Run calculations that credit transactions to reps/teams and compute payouts
4. Give each rep a personal dashboard to see credits, payouts, and raise disputes
5. Let admins review, adjust, and release results before reps see them

This is an MVP build. Do not attempt full feature parity with SalesCookie in one pass — build the core pipeline first (see Section 6, Phasing), and leave clear extension points for later phases.

---

## 2. Tech Stack (fixed — do not deviate without asking)

- **Backend:** PHP 8.3+, Laravel 11
- **Frontend:** Laravel Blade + Alpine.js + Tailwind CSS (server-rendered, minimal JS framework overhead). If a richer SPA-style admin UI is needed for the plan builder wizard, use Livewire components rather than a separate SPA framework.
- **Database:** MySQL 8 (matches existing CloudPanel/VPS hosting environment)
- **Queue/Jobs:** Laravel Queues (database driver for MVP, Redis-ready for later)
- **Auth:** Laravel Breeze/Fortify as the base, extended with the custom role model in Section 4
- **Hosting target:** self-hosted VPS via CloudPanel (assume standard LEMP stack; do not assume Docker unless asked)
- **File storage:** local disk for MVP, S3-compatible driver ready for later

Ask before introducing any other major dependency (e.g., a frontend framework, a different database, a SaaS billing provider).

---

## 3. Multi-Tenancy Model

- Single database, shared schema, **tenant_id (workspace_id) on every business table** — do not use separate databases per tenant for MVP.
- A "Workspace" is the top-level tenant. All users, plans, transactions, calculations, etc. belong to exactly one workspace.
- Enforce tenant isolation at the query level via a global Eloquent scope (`BelongsToWorkspace` trait/scope) applied to every tenant-scoped model — never rely on manually adding `where workspace_id = ...` in each query.
- Build this in from the very first migration. Do not defer multi-tenancy to a later phase.

---

## 4. Roles & Permissions

Four roles, scoped per workspace:

| Role | Access |
|---|---|
| **Full Admin** | Unrestricted control over the whole workspace |
| **Plan Admin** | Full control, but only over plans explicitly assigned to them |
| **Limited Admin** | Read-only access; specific plans can be hidden from them |
| **Participant** | Access only to their own personal dashboard (their credits, payouts, disputes) |

Implementation:
- `users` table with a `role` enum column (`full_admin`, `plan_admin`, `limited_admin`, `participant`) per workspace membership (a user could theoretically belong to multiple workspaces — support that via a `workspace_user` pivot table with the role on the pivot, not on the user).
- `plan_admin_assignments` pivot table (`plan_id`, `user_id`) for Plan Admin scoping.
- `plan_visibility` rules for Limited Admins (a plan can be flagged hidden from specific limited-admin users).
- Use Laravel Policies for all authorization checks — one policy per major model (PlanPolicy, TransactionPolicy, CalculationPolicy, DisputePolicy, etc.).

---

## 5. Core Data Model (build these migrations first, in this order)

Design and implement Eloquent models + migrations for:

**Tenancy & Identity**
- `workspaces`
- `users`, `workspace_user` (pivot with role)

**Plan Design**
- `plans` (name, description, period_type [monthly/quarterly/annual/custom], start_date, end_date, status [draft/active/archived], performance_metric [revenue/profit/custom], currency)
- `plan_versions` — **critical**: every time a plan is submitted for calculation, snapshot its full config here (JSON column is acceptable) so historical calculations remain reproducible even after the live plan is edited. Do not skip this — it's the audit backbone of the whole system.
- `plan_tiers` (plan_id, threshold_from, threshold_to, rate_or_amount, cumulative boolean)
- `reward_rules` (plan_id, reward_type [cash_fixed, cash_pct_revenue, cash_pct_profit, cash_pct_salary, badge, email, announcement, prize], value)
- `plan_terms` (legal text, versioned) + `enrollments` (user_id, plan_id, enrolled_at, signature data — text/timestamp acceptable for MVP, no need for a full e-sign integration yet)

**Transactions & Crediting**
- `import_sources` (workspace_id, type [csv/api/manual], config JSON, last_synced_at)
- `transactions` (workspace_id, external_id, source_system, raw_data JSON, amount, profit_amount, currency, transaction_date, deduplication key = `external_id` + `source_system` — imports must be idempotent upserts, never blind inserts)
- `aliases` (workspace_id, user_id or team_id, alias_value, match_field) — this is the crediting mechanism: an alias is a keyword matched against a field on incoming transactions to determine who gets credited
- `crediting_rules` (plan_id, matching logic reference)

**Calculation Engine**
- `calc_runs` (plan_id, plan_version_id, status [running/completed/failed], is_simulation boolean, started_at, completed_at, triggered_by_user_id)
- `calc_logs` (calc_run_id, transaction_id nullable, step_description, amount_before, amount_after) — every rule application against every transaction must be logged here; this is what makes disputes resolvable
- `credits` (calc_run_id, transaction_id, user_id or team_id, credited_amount, status [pending/reviewed/released])
- `rewards` (calc_run_id, user_id, plan_id, reward_type, computed_amount, status [pending/reviewed/released])

**Dashboards & Disputes**
- `disputes` (user_id, plan_id, calc_run_id nullable, transaction_id nullable, category, description, status [open/investigating/resolved], resolution_notes)
- `dispute_comments` (dispute_id, user_id, comment, created_at)
- `announcements` (workspace_id, title, body, audience [all/specific_users], published_at)

**Currency (stub for MVP, real for Phase 2)**
- `currencies`, `fx_rates` (rate, effective_date) — build the schema now even if Phase 1 only supports single-currency workspaces, so Phase 2 doesn't require a data model rewrite.

For every table, include `created_at`, `updated_at`, and `workspace_id` (except on tables that inherit tenancy through a parent, e.g. `plan_tiers` through `plans`).

---

## 6. Build Phasing

**Phase 1 — MVP (build this fully; this is the primary deliverable of this prompt):**
- Workspace + auth + 4-role RBAC
- Plan builder: quotas, tiers (cumulative/non-cumulative), one performance metric per plan, cash + non-cash reward types
- CSV transaction import with column auto-mapping (best-effort auto-detect + manual override UI) and idempotent upsert
- Alias-based crediting engine
- Calculation engine: run a plan → snapshot plan version → generate calc log → generate credits → generate rewards
- Admin review screens: Credits (pending → reviewed → released) and Rewards (pending → reviewed → released), matching SalesCookie's two-stage release pipeline — do not let rep-facing dashboards show anything before it's explicitly released
- Rep dashboard: view released credits, view released payouts/statements, submit a dispute (with category + description + optional linked transaction)
- Admin dispute queue: view, comment, resolve
- Basic built-in reports: payout by user, payout by plan, crediting by product/customer (simple filtered tables/exports are fine for MVP — no BI integration yet)

**Phase 2 (do not build yet — just leave clean extension points):**
- Multi-currency with live FX
- Generic REST API + webhook puller + Zapier-style integration framework
- Native CRM/ERP connectors (Salesforce, HubSpot, QuickBooks, etc.)
- SSO (cloud-provider OAuth first, then SAML/OIDC for enterprise)
- Simulation mode (projected payouts using sampled historical transaction pace)
- ASC 606 amortization reporting, Power BI/OData export
- Formal e-signature integration for plan enrollment

When building Phase 1 code, keep interfaces (e.g., an `ImportSource` interface, a `Crediting Strategy` interface) generic enough that Phase 2 connectors can be added without refactoring the core.

---

## 7. Non-Functional Requirements

- **Auditability is a first-class requirement, not an afterthought.** Every calculation must be fully traceable: which transaction, which rule, which resulting amount. Do not build a calculation engine that only stores final results.
- **Idempotency on all imports.** Re-importing the same file/data must never create duplicate transactions.
- **Multi-tenant data isolation must be enforced at the ORM layer**, not just in application logic — write an automated test that verifies workspace A can never see workspace B's data even via a crafted request.
- **Queue long-running calculations.** A calc run over any non-trivial transaction volume must run as a queued job with a status the UI can poll, not a synchronous request.
- **Write feature tests (PHPUnit/Pest) for:** the crediting engine matching logic, the tiered-commission math, the plan-versioning/snapshot behavior, and role-based access boundaries. These four areas are where bugs are costliest.

---

## 8. What to Do First

1. Scaffold the Laravel project, auth, and workspace/RBAC model (Sections 3–4).
2. Write and run migrations for the full data model in Section 5.
3. Build the Plan builder (create/edit plan, tiers, reward rules) — admin-only screens.
4. Build CSV import with column mapping.
5. Build the calculation engine as a queued job, producing calc_logs, credits, and rewards.
6. Build the admin review/release screens for Credits and Rewards.
7. Build the participant dashboard (read-only released data + dispute submission).
8. Build the admin dispute queue.
9. Add the three basic reports.
10. Write the four categories of tests from Section 7.

**Ask me before proceeding if:**
- You need a decision on exact tiered-commission formula behavior (e.g., how partial-period proration should work) that isn't specified above.
- You're about to introduce a new major dependency not listed in Section 2.
- The CSV auto-mapping heuristic needs a judgment call on ambiguous column names.

Otherwise, proceed autonomously through the steps above and flag assumptions made along the way in commit messages or a running `DECISIONS.md` file at the project root.
