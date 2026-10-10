<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\ActivityLogger;
use App\Services\Billing\SubscriptionManager;
use App\Services\Billing\UsageMeter;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Subscription & usage management (Full Admin). Shows metered usage against the
 * current tier, lets admins change tier or start a trial. Stripe charging is
 * scaffolded — tier changes apply immediately in self-serve mode.
 */
class BillingController extends Controller
{
    public function __construct(
        protected UsageMeter $meter,
        protected SubscriptionManager $subscriptions,
    ) {}

    public function index(): View
    {
        $this->authorizeFullAdmin();
        $workspace = $this->workspace();

        return view('admin.billing.index', [
            'workspace' => $workspace,
            'usage' => $this->meter->snapshot($workspace),
            'tiers' => $this->subscriptions->tiers(),
            'stripeEnabled' => $this->subscriptions->stripeEnabled(),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $data = $request->validate([
            'tier' => ['required', 'string', 'in:'.implode(',', array_keys($this->subscriptions->tiers()))],
        ]);

        $workspace = $this->workspace();
        $previousTier = $workspace->tier();

        if (! $this->subscriptions->changeTier($workspace, $data['tier'])) {
            $cap = $this->subscriptions->tiers()[$data['tier']]['max_payees'] ?? null;

            return redirect()->route('admin.billing.index')->withErrors(['tier' => $cap !== null
                ? "That plan allows {$cap} members and you have {$workspace->users()->count()}. Remove members first."
                : 'Could not change the subscription tier.']);
        }

        $logger->log('billing.tier_changed', "Changed subscription from {$previousTier} to {$data['tier']}.", $workspace, [
            'changes' => ['tier' => ['old' => $previousTier, 'new' => $data['tier']]],
        ]);

        return redirect()->route('admin.billing.index')->with('status', 'Subscription updated.');
    }

    public function startTrial(ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $workspace = $this->workspace();
        $started = $this->subscriptions->startTrial($workspace);

        if ($started) {
            $logger->log('billing.trial_started', 'Started a free trial.', $workspace, [
                'trial_ends_at' => $workspace->fresh()->trial_ends_at?->toIso8601String(),
            ]);
        }

        return redirect()->route('admin.billing.index')->with(
            'status',
            $started ? 'Your trial has started.' : 'A trial is not available for this workspace.',
        );
    }

    protected function authorizeFullAdmin(): void
    {
        abort_unless(request()->user()?->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }

    protected function workspace(): Workspace
    {
        return Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id());
    }
}
