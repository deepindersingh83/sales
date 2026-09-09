<?php

namespace Tests\Feature\Participant;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_released_data_for_the_current_user(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->actingAsMember($ws, Role::Participant);
        $other = $this->makeMember($ws, Role::Participant);

        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        // Released + pending credit for the rep, plus a released credit for someone else.
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'credited_amount' => 1000, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'credited_amount' => 500, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $other->id, 'credited_amount' => 9999, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 200, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 77, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Only the rep's released credit (1000) — not their pending (500) nor the other rep's.
        $this->assertEqualsWithDelta(1000.0, $response->viewData('totalCredited'), 0.01);
        // Only the rep's released reward (200), not pending (77).
        $this->assertEqualsWithDelta(200.0, $response->viewData('totalPayout'), 0.01);
    }

    public function test_admin_dashboard_does_not_use_participant_view(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard');
    }
}
