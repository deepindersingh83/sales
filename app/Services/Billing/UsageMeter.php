<?php

namespace App\Services\Billing;

use App\Enums\PayoutStatus;
use App\Models\Reward;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Metering for per-active-payee billing. An "active payee" is a workspace
 * member who received at least one released payout within a billing period
 * (defaults to the current calendar month). Charges are computed from the
 * workspace's tier: usage above the included count is billed per payee.
 */
class UsageMeter
{
    /**
     * Distinct users with a released reward in the given period.
     */
    public function activePayees(Workspace $workspace, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return Reward::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('status', PayoutStatus::Released)
            ->whereBetween('updated_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * A full usage snapshot for display and billing.
     *
     * @return array{tier:string, active_payees:int, included:int, billable:int, rate:float, charge:float, currency:string, limit:?int, over_limit:bool}
     */
    public function snapshot(Workspace $workspace): array
    {
        $config = $workspace->tierConfig();
        $active = $this->activePayees($workspace);
        $included = (int) ($config['included_payees'] ?? 0);
        $rate = (float) ($config['price_per_payee'] ?? 0);
        $billable = max(0, $active - $included);
        $limit = $workspace->payeeLimit();

        return [
            'tier' => $workspace->tier(),
            'active_payees' => $active,
            'included' => $included,
            'billable' => $billable,
            'rate' => $rate,
            'charge' => round($billable * $rate, 2),
            'currency' => config('billing.currency', 'USD'),
            'limit' => $limit,
            'over_limit' => $limit !== null && $active >= $limit,
        ];
    }

    /**
     * How many distinct payees the workspace would have this period if it added
     * one more — used to gate onboarding on capped tiers.
     */
    public function wouldExceedLimit(Workspace $workspace): bool
    {
        $limit = $workspace->payeeLimit();

        if ($limit === null) {
            return false;
        }

        // Count seats (members) rather than active payees for the gate: adding a
        // member is what pushes a free workspace over its cap.
        return $workspace->users()->count() >= $limit;
    }
}
