<?php

namespace App\Services\Reporting;

use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Restricts report queries to the plans the signed-in admin may see: plan
 * admins only their own plans, limited admins everything not hidden from
 * them (User::canViewPlan). Full admins, and contexts with no signed-in user
 * (API token, console), are unrestricted.
 */
class ReportScope
{
    /** @var Collection<int, int>|null|false false = not yet resolved */
    protected Collection|false|null $planIds = false;

    public function __construct(protected AuthFactory $auth) {}

    /**
     * Visible plan ids, or null when unrestricted.
     *
     * @return Collection<int, int>|null
     */
    public function planIds(): ?Collection
    {
        if ($this->planIds !== false) {
            return $this->planIds;
        }

        $user = $this->auth->guard()->user();

        if (! $user instanceof User || $user->currentRole() === Role::FullAdmin) {
            return $this->planIds = null;
        }

        return $this->planIds = Plan::query()->get()
            ->filter(fn (Plan $plan) => $user->canViewPlan($plan))
            ->pluck('id')
            ->values();
    }

    /** Limit a Reward query to visible plans. */
    public function rewards(Builder $query): Builder
    {
        $ids = $this->planIds();

        return $ids === null ? $query : $query->whereIn('plan_id', $ids);
    }

    /** Limit a Credit query to runs of visible plans. */
    public function credits(Builder $query): Builder
    {
        $ids = $this->planIds();

        return $ids === null
            ? $query
            : $query->whereIn('calc_run_id', CalcRun::query()->whereIn('plan_id', $ids)->select('id'));
    }
}
