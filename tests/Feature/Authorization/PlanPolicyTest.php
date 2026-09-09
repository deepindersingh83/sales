<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role-based access boundaries around plans — one of the four costliest bug
 * areas called out by the spec.
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_admin_can_create_update_and_delete_any_plan(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::FullAdmin);
        $plan = Plan::factory()->for($ws)->create();

        $this->assertTrue($user->can('create', Plan::class));
        $this->assertTrue($user->can('update', $plan));
        $this->assertTrue($user->can('delete', $plan));
        $this->assertTrue($user->can('view', $plan));
    }

    public function test_plan_admin_only_administers_assigned_plans(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::PlanAdmin);

        $assigned = Plan::factory()->for($ws)->create();
        $other = Plan::factory()->for($ws)->create();
        $assigned->assignedAdmins()->attach($user->id);

        // Cannot create brand-new plans.
        $this->assertFalse($user->can('create', Plan::class));

        // Can manage the assigned plan, not the other.
        $this->assertTrue($user->can('update', $assigned));
        $this->assertTrue($user->can('view', $assigned));
        $this->assertFalse($user->can('update', $other));
        $this->assertFalse($user->can('view', $other));

        // Deleting is Full-Admin-only even for assigned plans.
        $this->assertFalse($user->can('delete', $assigned));
    }

    public function test_limited_admin_is_read_only_and_respects_hidden_plans(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::LimitedAdmin);

        $visible = Plan::factory()->for($ws)->create();
        $hidden = Plan::factory()->for($ws)->create();
        $hidden->hiddenFromUsers()->attach($user->id);

        $this->assertTrue($user->can('view', $visible));
        $this->assertFalse($user->can('view', $hidden));

        // Read-only: no writes at all.
        $this->assertFalse($user->can('create', Plan::class));
        $this->assertFalse($user->can('update', $visible));
        $this->assertFalse($user->can('delete', $visible));
    }

    public function test_participant_cannot_access_plan_admin_surface(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::Participant);
        $plan = Plan::factory()->for($ws)->create();

        $this->assertFalse($user->can('viewAny', Plan::class));
        $this->assertFalse($user->can('view', $plan));
        $this->assertFalse($user->can('create', Plan::class));
        $this->assertFalse($user->can('update', $plan));
    }
}
