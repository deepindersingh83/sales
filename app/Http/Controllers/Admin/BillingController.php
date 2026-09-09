<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
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

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $data = $request->validate([
            'tier' => ['required', 'string', 'in:'.implode(',', array_keys($this->subscriptions->tiers()))],
        ]);

        $changed = $this->subscriptions->changeTier($this->workspace(), $data['tier']);

        return redirect()->route('admin.billing.index')->with(
            'status',
            $changed ? 'Subscription updated.' : 'Could not change the subscription tier.',
        );
    }

    public function startTrial(): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $started = $this->subscriptions->startTrial($this->workspace());

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
