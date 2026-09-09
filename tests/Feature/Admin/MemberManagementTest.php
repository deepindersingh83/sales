<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_admin_can_add_a_new_member_with_a_role(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.members.store'), [
            'name' => 'New Rep', 'email' => 'rep@example.com', 'role' => Role::Participant->value,
        ])->assertRedirect();

        $member = User::where('email', 'rep@example.com')->firstOrFail();
        $this->assertSame(Role::Participant, $member->roleIn($ws));
    }

    public function test_full_admin_can_change_and_a_last_full_admin_cannot_be_demoted(): void
    {
        $ws = Workspace::factory()->create();
        $owner = $this->actingAsMember($ws, Role::FullAdmin);
        $member = $this->makeMember($ws, Role::Participant);

        // Promote member to Plan Admin.
        $this->put(route('admin.members.update', $member), ['role' => Role::PlanAdmin->value])->assertRedirect();
        $this->assertSame(Role::PlanAdmin, $member->roleIn($ws));

        // The sole Full Admin (owner) cannot be demoted.
        $this->from(route('admin.members.index'))
            ->put(route('admin.members.update', $owner), ['role' => Role::Participant->value])
            ->assertSessionHasErrors('role');
        $this->assertSame(Role::FullAdmin, $owner->roleIn($ws));
    }

    public function test_non_full_admin_cannot_manage_team(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::PlanAdmin);

        $this->get(route('admin.members.index'))->assertForbidden();
        $this->post(route('admin.members.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'participant'])
            ->assertForbidden();
    }

    public function test_full_admin_can_assign_plan_admins_and_hide_from_limited(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $planAdmin = $this->makeMember($ws, Role::PlanAdmin);
        $limited = $this->makeMember($ws, Role::LimitedAdmin);
        $plan = Plan::factory()->for($ws)->create();

        $this->put(route('admin.plans.access.update', $plan), [
            'plan_admin_ids' => [$planAdmin->id],
            'hidden_user_ids' => [$limited->id],
        ])->assertRedirect();

        $this->assertTrue($plan->assignedAdmins()->where('users.id', $planAdmin->id)->exists());
        $this->assertTrue($plan->hiddenFromUsers()->where('users.id', $limited->id)->exists());
    }
}
