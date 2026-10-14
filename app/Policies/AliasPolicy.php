<?php

namespace App\Policies;

use App\Models\Alias;
use App\Models\User;

/**
 * Aliases are crediting configuration: viewable by any admin, editable only by
 * roles that can write (Full + Plan Admin).
 */
class AliasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function create(User $user): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function update(User $user, Alias $alias): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function delete(User $user, Alias $alias): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }
}
