# Engineering Decisions & Assumptions

This file records decisions and assumptions made while building the Sales Commission &
Incentive Management SaaS, per the build prompt's instruction to flag assumptions in a
running `DECISIONS.md`.

## Environment & Stack

- **D1 — Database for local/CI vs production.** Development and the automated test suite run
  against **SQLite** (the sandbox has no MySQL server). **Production targets MySQL 8** per the
  spec; this is a config-only difference in Laravel. Migrations avoid MySQL-only column types so
  they run on both. `.env.example` ships the MySQL production template.
- **D2 — Laravel version.** The spec pinned Laravel 11, but the installer provided **Laravel 13**
  and the user explicitly accepted it. Laravel 13 requires **PHP 8.4+** in production — the
  CloudPanel/VPS host must run PHP 8.4 or newer. (User-confirmed.)
- **D3 — Queue driver.** `database` queue driver for MVP (spec), Redis-ready for later by config.
- **D4 — Auth base.** Laravel Breeze (Blade + Alpine + Tailwind stack) as the auth base, extended
  with the custom workspace/role model. Livewire used for the richer multi-step admin wizards
  (plan builder), per the spec's allowance.

## Multi-Tenancy

- **D5 — Single shared schema.** One database, shared schema, `workspace_id` on every
  tenant-scoped business table. Isolation enforced at the ORM layer via a global Eloquent scope
  (`BelongsToWorkspace` trait + `WorkspaceScope`), never by hand-written `where` clauses.
- **D6 — Membership & roles.** A user can belong to multiple workspaces. Role lives on the
  `workspace_user` pivot, not on the user, so the same person can be a Full Admin in one
  workspace and a Participant in another.

## Calculation semantics (Phase 5 — pending user input)

- **D7 — Proration / tier math.** GATED. The user chose to specify the exact partial-period
  proration rule and cumulative-tier math before the calc engine is built. To be recorded here
  once provided.

## Open items / deferred (Phase 2, not built)

- Multi-currency + live FX (schema built now, single-currency behavior only).
- Generic REST API / webhook puller / connector framework (interfaces kept generic).
- SSO, simulation mode, ASC 606, e-signature.
