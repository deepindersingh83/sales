<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * Transactions are workspace-wide (not plan-scoped). Any admin may view them;
 * only roles that can write (Full + Plan Admin) may import/edit/delete.
 * Limited Admins are read-only; Participants have no access.
 */
class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function create(User $user): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    /** CSV import is a create operation. */
    public function import(User $user): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }
}
