<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a user and attach them to a workspace with the given role.
     */
    protected function makeMember(Workspace $workspace, Role $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    /**
     * Set the active workspace context (drives the global tenant scope).
     */
    protected function useWorkspace(Workspace $workspace): void
    {
        app(WorkspaceContext::class)->set($workspace);
    }

    /**
     * Authenticate as a member of the workspace with the given role, set the
     * workspace context + session, and return the user. Works for both HTTP
     * and direct-Gate tests.
     */
    protected function actingAsMember(Workspace $workspace, Role $role, array $attributes = []): User
    {
        $user = $this->makeMember($workspace, $role, $attributes);

        $this->useWorkspace($workspace);
        $this->actingAs($user);
        $this->withSession(['current_workspace_id' => $workspace->id]);

        return $user;
    }
}
