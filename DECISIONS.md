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

- **D11 — Split crediting (supersedes part of D8).** Crediting now considers **all** matching
  aliases, not just the first. Each matching alias credits its `split_percent` (default 100%) of
  the transaction to its user, enabling deal splits (e.g. two aliases at 50% each). A single
  matching alias still credits 100%. Overlapping aliases at 100% will double-credit by design —
  configure percentages for true splits.
- **D12 — Manager overrides.** `workspace_user.manager_id` defines a single-level reporting line;
  a plan's `manager_override_percent` pays each manager that % of their direct reports' credited
  attainment as an `override` reward. Multi-level rollups are a future extension.
- **D13 — PDF commission statements.** Built with `barryvdh/laravel-dompdf` (user-requested).
  A statement covers **released** data only. Credits fall in the period by transaction date
  (credit date when the transaction has none) and rewards by the date they were calculated —
  the same bucketing ReportBuilder uses. Amounts are never FX-converted; totals are grouped by
  currency. Members download their own; any admin role may download another member's, limited
  to the plans that admin may view (ReportScope). Remote images are disabled in dompdf (an
  admin-supplied `logo_url` would otherwise be fetched server-side), so the PDF header shows the
  brand name and colour, not the logo.
- **D14 — Activity log (audit trail).** Controllers call `App\Services\ActivityLogger` explicitly
  after an admin action succeeds (plans, plan access, calc runs, releases, adjustments, members,
  aliases, settings, API token, FX, billing, transactions, disputes, customers, and Xero: app
  keys saved/removed, connected, schedule changed, full resync, disconnected). The logger drops
  any property whose key looks like a credential (password/secret/token/credential/api key),
  works with no signed-in user (console: `user_id` null), and records salary changes as
  "changed" without the figures.
  **Who can view:** Full Admins and Limited Admins (read-only oversight). Plan Admins cannot —
  most entries are workspace-wide, not per plan. Limited Admins do not see entries whose subject
  is a plan hidden from them or a calc run of such a plan.
- **D15 — Commission estimator ("what if").** `CommissionCalculator` now holds the
  formula/tier/cap math the engine used inline, so the engine and `CommissionEstimator` share it.
  Current attainment = the rep's **released** credits on runs of the plan whose transactions fall
  in the plan's current window (same rules as the engine: plan dates, or quarter/year to date for
  qtd/ytd; unbounded when the plan has no dates). Released true-up credits are deltas, so summing
  them is correct; if a workspace releases two full (non-true-up) runs over the same window the
  attainment is double-counted, exactly as the released totals elsewhere would be. The deal is
  entered in the plan's metric and currency and added to attainment and to that metric's total;
  revenue/profit for formula plans are each credit's share of its transaction. Estimates use the
  plan's live configuration (what the next run would snapshot) and cover tier commission only —
  reward rules and manager overrides are excluded. Reps see active plans they are enrolled in or
  have released credits on; admins can estimate for any member on plans they may view.

## Open items / deferred (Phase 2, not built)

- Multi-currency + live FX (schema built now, single-currency behavior only).
- Generic REST API / webhook puller / connector framework (interfaces kept generic).
- SSO, simulation mode, ASC 606, e-signature.
