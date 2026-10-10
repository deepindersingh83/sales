<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real requests start with an empty WorkspaceContext — only the
 * ResolveWorkspace middleware fills it. These tests clear the context the
 * helpers pre-set, so route-model binding is exercised as in production.
 */
class RouteBindingTest extends TestCase
{
    use RefreshDatabase;

    private function actAsFreshRequest(User $user, Workspace $workspace): void
    {
        app(WorkspaceContext::class)->clear();
        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id]);
    }

    public function test_bound_records_resolve_once_the_workspace_is_resolved(): void
    {
        $ws = Workspace::factory()->create();
        $admin = $this->makeMember($ws, Role::FullAdmin);
        $this->useWorkspace($ws);
        $plan = Plan::factory()->for($ws)->create();

        $this->actAsFreshRequest($admin, $ws);

        $this->get(route('admin.plans.edit', $plan))->assertOk();
    }

    public function test_another_workspaces_record_is_still_not_found(): void
    {
        $ws = Workspace::factory()->create();
        $admin = $this->makeMember($ws, Role::FullAdmin);
        $other = Workspace::factory()->create();
        $this->useWorkspace($other);
        $foreignPlan = Plan::factory()->for($other)->create();

        $this->actAsFreshRequest($admin, $ws);

        $this->get(route('admin.plans.edit', $foreignPlan))->assertNotFound();
    }
}
