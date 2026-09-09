<?php

namespace App\Policies;

use App\Models\Dispute;
use App\Models\User;

/**
 * Participants raise and view their own disputes. Admins triage the workspace
 * queue: Full Admins see all; Plan Admins see disputes tied to their assigned
 * plans (or unlinked ones); Limited Admins have read-only queue access.
 */
class DisputePolicy
{
    public function viewAny(User $user): bool
    {
        // Everyone with a workspace role has *some* dispute view (own or queue).
        return $user->currentRole() !== null;
    }

    public function view(User $user, Dispute $dispute): bool
    {
        // The raiser can always see their own dispute.
        if ($dispute->user_id === $user->id) {
            return true;
        }

        $role = $user->currentRole();

        if ($role === null || ! $role->isAdmin()) {
            return false;
        }

        // Full & Limited admins see all workspace disputes; Plan admins only
        // those tied to a plan they administer (or disputes with no plan link).
        if ($role->isFullAdmin() || ! $role->canWrite()) {
            return true; // full admin, or limited admin (read-only)
        }

        return $dispute->plan === null || $user->administersPlan($dispute->plan);
    }

    /** Members raise disputes about their own data. */
    public function create(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function comment(User $user, Dispute $dispute): bool
    {
        if ($dispute->user_id === $user->id) {
            return true;
        }

        // Writers who can see it may comment; limited admins are read-only.
        return ($user->currentRole()?->canWrite() ?? false) && $this->view($user, $dispute);
    }

    /** Resolving is a writing admin action. */
    public function resolve(User $user, Dispute $dispute): bool
    {
        return ($user->currentRole()?->canWrite() ?? false) && $this->view($user, $dispute);
    }
}
