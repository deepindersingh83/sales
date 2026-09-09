<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Support\WorkspaceContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Workspaces this user belongs to, with the per-workspace role on the pivot.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot(['role', 'manager_id'])
            ->withTimestamps();
    }

    public function belongsToWorkspace(Workspace|int $workspace): bool
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this->workspaces()->where('workspaces.id', $id)->exists();
    }

    /**
     * The user's role in a given workspace, or null if not a member.
     */
    public function roleIn(Workspace|int $workspace): ?Role
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        $membership = $this->workspaces()->where('workspaces.id', $id)->first();

        return $membership
            ? Role::from($membership->pivot->role)
            : null;
    }

    /**
     * The user's role in the currently-resolved workspace context.
     */
    public function currentRole(): ?Role
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        return $workspaceId ? $this->roleIn($workspaceId) : null;
    }

    public function hasRoleInCurrentWorkspace(Role ...$roles): bool
    {
        $current = $this->currentRole();

        return $current !== null && in_array($current, $roles, true);
    }

    // --- Plan-scoped authorization helpers (used by policies) ---------------

    /**
     * May this user administer (edit/run) the given plan in the current
     * workspace? Full Admins can; Plan Admins only for assigned plans.
     */
    public function administersPlan(Plan $plan): bool
    {
        $role = $this->currentRole();

        if ($role === Role::FullAdmin) {
            return true;
        }

        if ($role === Role::PlanAdmin) {
            return $plan->assignedAdmins()
                ->where('users.id', $this->id)
                ->exists();
        }

        return false;
    }

    /**
     * May this user view the given plan's admin data? Full Admins and Plan
     * Admins (assigned) can; Limited Admins can unless the plan is hidden from
     * them; Participants cannot browse plan config.
     */
    public function canViewPlan(Plan $plan): bool
    {
        $role = $this->currentRole();

        return match ($role) {
            Role::FullAdmin => true,
            Role::PlanAdmin => $this->administersPlan($plan),
            Role::LimitedAdmin => ! $plan->hiddenFromUsers()->where('users.id', $this->id)->exists(),
            default => false,
        };
    }
}
