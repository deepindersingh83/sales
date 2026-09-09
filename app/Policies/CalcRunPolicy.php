<?php

namespace App\Policies;

use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\User;

/**
 * Calculation runs, plus the review/release pipeline for their credits/rewards.
 * View follows plan visibility; running and releasing follow plan admin rights.
 */
class CalcRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function view(User $user, CalcRun $run): bool
    {
        return $user->canViewPlan($run->plan);
    }

    /** Starting a calc run against a plan. */
    public function createForPlan(User $user, Plan $plan): bool
    {
        return $user->administersPlan($plan);
    }

    /** Moving credits/rewards through pending -> reviewed -> released. */
    public function release(User $user, CalcRun $run): bool
    {
        return $user->administersPlan($run->plan);
    }
}
