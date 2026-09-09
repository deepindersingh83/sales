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

- **D7 — Proration / tier math (user-confirmed).**
  - **Cumulative tiers** use **progressive/marginal** math: each tier's rate applies only to the
    portion of attainment within that tier's band. Each tier is evaluated independently by its
    own `is_cumulative` flag.
  - **Non-cumulative tiers**: the single tier whose `[from, to]` band contains total attainment
    applies its rate to the **entire** attainment; other non-cumulative tiers contribute nothing.
  - **`amount`-kind tiers** pay their `rate_or_amount` as a **flat cash bonus** once attainment
    reaches `threshold_from` (bonuses from multiple reached amount-tiers stack). `rate`-kind
    tiers use the tier math above.
  - **No partial-period proration** in the MVP: full commission on whatever transactions fall in
    the period, regardless of enrollment date. A proration hook is left for later.
  - **Attainment basis**: the plan's `performance_metric` — revenue plans use `amount`, profit
    plans use `profit_amount`. Attainment = sum of that metric over the rep's credited transactions.
- **D8 — Credits vs rewards.** A **credit** is per-transaction: `credited_amount` = the metric
  value credited to the rep. Each transaction is credited to the **first** matching alias's user
  (documented; multi-/split-crediting is a Phase-2 refinement); unmatched transactions are logged.
  A rep's tier **commission** is stored as a **Reward** with a dedicated `commission` reward type;
  `reward_rules` produce additional cash/non-cash rewards. `cash_pct_salary` records a note (no
  salary data model in MVP).

- **D9 — Reports scope.** The three built-in reports (payout by user, payout by plan, crediting
  by product/customer) aggregate **released** data only — the final numbers reps and finance see —
  with CSV export. The crediting report groups by any `raw_data` field present on the credited
  transactions (default `product`).
- **D10 — Reward-rule percentage values.** `cash_pct_*` reward-rule values are stored as fractions
  (e.g. `0.02` = 2%), consistent with tier rates.

## Open items / deferred (Phase 2, not built)

- Multi-currency + live FX (schema built now, single-currency behavior only).
- Generic REST API / webhook puller / connector framework (interfaces kept generic).
- SSO, simulation mode, ASC 606, e-signature.
