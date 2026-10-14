<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_second_company_and_owns_it(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('workspaces.store'), ['name' => 'Second Co', 'base_currency' => 'gbp'])
            ->assertRedirect(route('dashboard'));

        $second = Workspace::where('name', 'Second Co')->firstOrFail();
        $this->assertSame(Role::FullAdmin, $user->roleIn($second));
        $this->assertSame('GBP', $second->base_currency);
        // Session switched to the new company.
        $this->assertSame($second->id, session('current_workspace_id'));
    }

    public function test_user_can_switch_between_their_companies_but_not_into_others(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $foreign = Workspace::factory()->create();

        $user = $this->actingAsMember($a, Role::FullAdmin);
        $b->users()->attach($user->id, ['role' => Role::Participant->value]);

        // Can switch into B (a member).
        $this->post(route('workspaces.switch', $b))->assertRedirect(route('dashboard'));
        $this->assertSame($b->id, session('current_workspace_id'));

        // Cannot switch into a workspace they don't belong to.
        $this->post(route('workspaces.switch', $foreign))->assertForbidden();
    }

    public function test_role_can_differ_per_company(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $user = $this->makeMember($a, Role::FullAdmin);
        $b->users()->attach($user->id, ['role' => Role::Participant->value]);

        $this->assertSame(Role::FullAdmin, $user->roleIn($a));
        $this->assertSame(Role::Participant, $user->roleIn($b));
    }
}
