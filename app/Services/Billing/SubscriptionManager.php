<?php

namespace App\Services\Billing;

use App\Models\Workspace;

/**
 * Manages a workspace's subscription state. Tier changes and trials are applied
 * locally; real invoicing is delegated to Stripe when configured (scaffolded —
 * see docs/INTEGRATIONS.md). Without a Stripe secret the manager operates in
 * "self-serve" mode: tier changes take effect immediately with no charge.
 */
class SubscriptionManager
{
    /** Whether real Stripe billing is wired up. */
    public function stripeEnabled(): bool
    {
        return ! empty(config('billing.stripe.secret'));
    }

    /** @return array<string, array<string, mixed>> */
    public function tiers(): array
    {
        return config('billing.tiers');
    }

    /**
     * Switch the workspace to a new tier. Returns whether the change applied.
     */
    public function changeTier(Workspace $workspace, string $tier): bool
    {
        if (! array_key_exists($tier, $this->tiers())) {
            return false;
        }

        // When Stripe is enabled this is where we would create/update the
        // subscription and only persist on webhook confirmation. In scaffold
        // mode we apply immediately.
        $workspace->update([
            'subscription_tier' => $tier,
            'subscription_status' => 'active',
        ]);

        return true;
    }

    /**
     * Start a free trial (no-op if one is already active or the workspace is on
     * a paid tier).
     */
    public function startTrial(Workspace $workspace): bool
    {
        if ($workspace->onTrial() || $workspace->onPaidTier()) {
            return false;
        }

        $workspace->update([
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ]);

        return true;
    }
}
