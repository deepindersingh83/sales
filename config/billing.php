<?php

/*
|--------------------------------------------------------------------------
| SaaS Billing & Subscription Tiers
|--------------------------------------------------------------------------
|
| Metering is per "active payee" — a workspace member who received a released
| payout inside the current billing period. Each tier includes a number of
| payees; usage above the included count is billed at the per-payee rate.
| The Free tier is a hard cap (no overage). Stripe charging is scaffolded —
| supply STRIPE_SECRET to wire real invoicing (see docs/INTEGRATIONS.md).
|
*/

return [

    // Length of the default trial when a workspace starts one.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    'default_tier' => env('BILLING_DEFAULT_TIER', 'free'),

    'tiers' => [
        'free' => [
            'name' => 'Free',
            'price_per_payee' => 0,
            'included_payees' => 3,
            'max_payees' => 3,        // hard cap — cannot exceed on this tier
            'features' => ['Core plans & calculations', 'Up to 3 active payees', 'CSV import'],
        ],
        'business' => [
            'name' => 'Business',
            'price_per_payee' => 30,
            'included_payees' => 0,
            'max_payees' => null,     // unlimited
            'features' => ['Everything in Free', 'Unlimited payees', 'Recurring imports', 'REST API'],
        ],
        'business_plus' => [
            'name' => 'Business+',
            'price_per_payee' => 50,
            'included_payees' => 0,
            'max_payees' => null,
            'features' => ['Everything in Business', 'Contests & gamification', 'White-labelling', 'Priority support'],
        ],
    ],

    // Currency used to display metered charges.
    'currency' => env('BILLING_CURRENCY', 'USD'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
