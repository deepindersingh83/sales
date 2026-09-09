<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_admin_can_create_a_plan_with_tiers_and_rewards(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $response = $this->post(route('admin.plans.store'), [
            'name' => 'Q3 Field Sales',
            'description' => 'Quarterly plan',
            'period_type' => 'quarterly',
            'status' => PlanStatus::Active->value,
            'performance_metric' => 'revenue',
            'currency' => 'usd',
            'tiers' => [
                ['threshold_from' => 0, 'threshold_to' => 10000, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => 1],
                ['threshold_from' => 10000, 'threshold_to' => '', 'kind' => 'rate', 'rate_or_amount' => 0.08, 'is_cumulative' => 1],
            ],
            'reward_rules' => [
                ['reward_type' => 'cash_fixed', 'value' => 500, 'label' => 'Bonus'],
                ['reward_type' => 'badge', 'value' => '', 'label' => 'Top Closer'],
            ],
        ]);

        $plan = Plan::where('name', 'Q3 Field Sales')->firstOrFail();
        $response->assertRedirect(route('admin.plans.show', $plan));

        $this->assertSame('USD', $plan->currency);            // upper-cased
        $this->assertSame(PlanStatus::Active, $plan->status);
        $this->assertCount(2, $plan->tiers);
        $this->assertEqualsWithDelta(0.08, (float) $plan->tiers[1]->rate_or_amount, 0.0001);
        $this->assertNull($plan->tiers[1]->threshold_to);     // blank -> open-ended
        $this->assertCount(2, $plan->rewardRules);
        $this->assertSame('Top Closer', $plan->rewardRules[1]->meta['label']);
    }

    public function test_editing_replaces_tiers(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $plan = Plan::factory()->for($ws)->create();
        $plan->tiers()->create(['threshold_from' => 0, 'kind' => 'rate', 'rate_or_amount' => 0.03, 'sort_order' => 0]);

        $this->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'period_type' => 'monthly',
            'status' => PlanStatus::Active->value,
            'performance_metric' => 'revenue',
            'currency' => 'USD',
            'tiers' => [
                ['threshold_from' => 0, 'threshold_to' => '', 'kind' => 'amount', 'rate_or_amount' => 250, 'is_cumulative' => 0],
            ],
            'reward_rules' => [],
        ])->assertRedirect();

        $plan->refresh()->load('tiers');
        $this->assertCount(1, $plan->tiers);
        $this->assertSame('amount', $plan->tiers[0]->kind);
        $this->assertFalse($plan->tiers[0]->is_cumulative);
    }

    public function test_validation_rejects_bad_tier_and_missing_name(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->from(route('admin.plans.create'))
            ->post(route('admin.plans.store'), [
                'name' => '',
                'period_type' => 'monthly',
                'status' => 'active',
                'performance_metric' => 'revenue',
                'currency' => 'USD',
                'tiers' => [
                    ['threshold_from' => 100, 'threshold_to' => 50, 'kind' => 'rate', 'rate_or_amount' => 0.05],
                ],
            ])
            ->assertSessionHasErrors(['name', 'tiers.0.threshold_to']);
    }

    public function test_admin_can_render_index_create_and_show(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $plan = Plan::factory()->for($ws)->create();

        $this->get(route('admin.plans.index'))->assertOk()->assertSee($plan->name);
        $this->get(route('admin.plans.create'))->assertOk()->assertSee('Commission tiers');
        $this->get(route('admin.plans.show', $plan))->assertOk()->assertSee($plan->name);
    }

    public function test_participant_cannot_reach_admin_plans(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);

        $this->get(route('admin.plans.index'))->assertForbidden();
        $this->get(route('admin.plans.create'))->assertForbidden();
    }

    public function test_plan_admin_cannot_create_but_can_edit_assigned(): void
    {
        $ws = Workspace::factory()->create();
        $user = $this->actingAsMember($ws, Role::PlanAdmin);
        $plan = Plan::factory()->for($ws)->create();

        // Cannot create.
        $this->get(route('admin.plans.create'))->assertForbidden();

        // Cannot edit unassigned.
        $this->get(route('admin.plans.edit', $plan))->assertForbidden();

        // Can edit once assigned.
        $plan->assignedAdmins()->attach($user->id);
        $this->get(route('admin.plans.edit', $plan))->assertOk();
    }
}
