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
- **Multi-currency**, **quota/cap/formula plans**, **splits**, **manager
  overrides**, **manual adjustments**, **double-payment detection**,
  **ASC 606 amortization report**, **simulation**, **typed-name enrollment
  e-signature**.

## Scaffolded (supply credentials / a driver)

### CRM / ERP / accounting connectors
`App\Services\Connectors\ConnectorRegistry` lists Salesforce, HubSpot, Dynamics,
Pipedrive, Zoho, NetSuite, QuickBooks, Xero, Stripe, PayPal, Snowflake. To make
one live: implement `App\Contracts\ImportSourceDriver` (return normalised rows —
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

## Notes
- ASC 606 here is straight-line amortization of released commissions over a
  configurable number of months; wire real contract terms per deal for
  deal-level schedules.
