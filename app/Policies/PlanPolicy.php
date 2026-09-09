<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    /** Any admin role can reach the plan list (rows are further filtered per-plan). */
    public function viewAny(User $user): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->canViewPlan($plan);
    }

    /** Only Full Admins create new plans. */
    public function create(User $user): bool
    {
        return $user->currentRole() === Role::FullAdmin;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->administersPlan($plan);
    }

    /** Managing tiers, reward rules, terms, and enrollments follows update rights. */
    public function manageConfig(User $user, Plan $plan): bool
    {
        return $user->administersPlan($plan);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->currentRole() === Role::FullAdmin;
    }

    /** Assigning Plan Admins / visibility is a Full Admin action. */
    public function manageAccess(User $user, Plan $plan): bool
    {
        return $user->currentRole() === Role::FullAdmin;
    }
}
