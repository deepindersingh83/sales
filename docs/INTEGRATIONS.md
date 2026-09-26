# Integrations & external services

Some capabilities need external accounts or credentials and ship as **wired
scaffolds** — the interfaces, config, and UI exist; supply credentials (and, for
connectors, a driver) to go live. What is fully live today is listed first.

## Live today (no external account needed)
- **CSV import** — upload with auto column-mapping + idempotent upsert.
- **REST API / webhooks** — `POST /api/v1/transactions` (ingest) with a
  per-workspace bearer token. See README.
- **BI feed** — `GET /api/v1/payouts` returns released rewards as JSON for
  Power BI / Tableau.
- **OData v4 feed** — `/api/odata` (entity sets `Payouts`, `Credits`,
  `Transactions`; `$metadata`, `$filter` with eq/ne/gt/ge/lt/le + `and`,
  `$orderby`, `$select`, `$top`, `$skip`, `$count`, server paging via
  `@odata.nextLink`). In Power BI / Excel use **Get Data → OData feed** with
  **Basic** auth: any user name, the workspace API token as password.
- **Xero** — see below.
- **Enterprise reporting** — revenue analytics (10 breakdowns, date range,
  growth, base-currency conversion), payout (user/plan/type/month), crediting
  (rep/plan/month/source/any field + uncredited exceptions), attainment
  (user, quota %, plan, team, distribution), liability, ASC 606 and the
  analytics dashboard. Every report exports CSV (UTF-8 BOM, opens in Excel).
- **Multi-currency**, **quota/cap/formula plans**, **splits**, **manager
  overrides**, **manual adjustments**, **double-payment detection**,
  **ASC 606 amortization report**, **simulation**, **typed-name enrollment
  e-signature**.

## Xero (live)

1. Create a Web app at developer.xero.com. Set its redirect URI to
   `https://<host>/admin/connectors/xero/callback`.
2. Set `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET` (and `XERO_REDIRECT_URI` if the
   default above differs) in `.env`. `XERO_SCOPES` defaults to
   `offline_access accounting.transactions.read accounting.contacts.read`;
   Xero apps created with granular scopes need the equivalent invoice read
   scope instead.
3. An admin opens **Integrations → Connect Xero** and authorises one or more
   organisations. Each becomes a daily recurring import source (the hourly
   `imports:run-scheduled` scheduler picks it up; **Run now** syncs at once).

What is imported: sales invoices (ACCREC) with status AUTHORISED or PAID.
`amount` = SubTotal (net of tax), currency and invoice date from Xero, PAID →
`is_paid` (drives pay-when-paid), later VOIDED → `excluded`. `raw_data` holds
`customer`, `invoice_number`, `reference`, `product` (first item code),
`item_codes`, totals, and every line-item tracking category in snake_case —
e.g. a "Sales Rep" tracking category becomes `sales_rep`, so an alias on
`sales_rep` credits the right rep. After the first full pull, syncs are
incremental (`If-Modified-Since`). OAuth tokens are stored encrypted
(`import_sources.credentials`) and refreshed/rotated automatically; the last
failure is shown on the Recurring imports page. Credit notes are not imported
yet.

## Coming soon (scaffolded — supply a driver)

### Other CRM / ERP / accounting connectors
`App\Services\Connectors\ConnectorRegistry` lists Salesforce, HubSpot, Dynamics,
Pipedrive, Zoho, NetSuite, QuickBooks, Stripe, PayPal, Snowflake as "coming
soon". To make one live (follow the Xero driver in
`App\Services\Connectors\Xero`): implement `App\Contracts\ImportSourceDriver` (return normalised rows —
the same shape the CSV path produces), register it, and store the customer's
credentials (the registry already declares each connector's config fields).
Nothing downstream (crediting, calc, release) changes.

### SSO (OAuth / SAML)
Config lives at `config/services.php → sso`. Enabling requires an OAuth/SAML
package (e.g. Laravel Socialite) plus the provider's client id/secret in
`.env` (`SSO_ENABLED`, `SSO_PROVIDER`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`).

### AI assistant (payee Q&A)
`App\Services\Ai\AiAssistant` runs as a stub until `services.ai.key`
(`AI_API_KEY`) is set. Implement the provider call in `AiAssistant::answer()`,
grounding it in the payee's released credits/rewards + calc logs.

### Formal e-signature
Enrollment already captures a typed-name signature + timestamp. A provider
(e.g. DocuSign) is an additive upgrade: swap the sign action for a provider
envelope and store the returned envelope id alongside the existing fields.

### Billing / subscriptions (Stripe)
Tiers, per-active-payee metering, trials and the Free-tier payee cap are fully
live and enforced locally (`config/billing.php`, `App\Services\Billing\`).
Metering counts distinct members who received a released payout in the period.
Without `STRIPE_SECRET` the app runs in **self-serve** mode: tier changes apply
immediately and no card is charged. To go live, set `STRIPE_SECRET` /
`STRIPE_WEBHOOK_SECRET` and implement the customer/subscription calls in
`SubscriptionManager::changeTier()` (persist on webhook confirmation), reporting
metered usage from `UsageMeter::snapshot()`.

## Notes
- ASC 606 here is straight-line amortization of released commissions over a
  configurable number of months; wire real contract terms per deal for
  deal-level schedules.
