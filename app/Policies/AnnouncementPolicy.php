<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Announcements are viewable by any admin and managed by roles that can write
 * (Full + Plan Admin). Participants only see published ones (handled in the
 * dashboard query, not here).
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole()?->isAdmin() ?? false;
    }

    public function create(User $user): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->currentRole()?->canWrite() ?? false;
    }
}
