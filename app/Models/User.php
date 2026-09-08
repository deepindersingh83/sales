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
            ->withPivot(['role'])
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
}
