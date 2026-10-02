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

        // Moving onto a capped tier with more members than it allows would
        // leave the workspace over its limit; members must be removed first.
        $cap = $this->tiers()[$tier]['max_payees'] ?? null;
        if ($cap !== null && ! $workspace->onTrial() && $workspace->users()->count() > $cap) {
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
     * Start the workspace's one free trial (no-op on a paid tier, or once a
     * trial has ever been started — an expired trial cannot be restarted).
     */
    public function startTrial(Workspace $workspace): bool
    {
        if ($workspace->trial_ends_at !== null || $workspace->onPaidTier()) {
            return false;
        }

        $workspace->update([
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ]);

        return true;
    }
}
