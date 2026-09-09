<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\RewardType;
use App\Models\Alias;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMechanicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_splits_credit_multiple_reps(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $ws->users()->attach($alice->id, ['role' => 'participant']);
        $ws->users()->attach($bob->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);

        // Same transaction matched by two aliases, 60/40 split on the "team" field.
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $alice->id, 'alias_value' => 'east', 'match_field' => 'team', 'match_type' => 'exact', 'split_percent' => 60]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $bob->id, 'alias_value' => 'east', 'match_field' => 'team', 'match_type' => 'exact', 'split_percent' => 40]);

        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 10000, 'currency' => 'USD', 'raw_data' => ['team' => 'east']]);

        $run = app(StartCalcRun::class)->handle($plan);

        // Two credits: 6000 to Alice, 4000 to Bob.
        $this->assertSame(2, Credit::where('calc_run_id', $run->id)->count());
        $this->assertEqualsWithDelta(6000.0, (float) Credit::where('calc_run_id', $run->id)->where('user_id', $alice->id)->value('credited_amount'), 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) Credit::where('calc_run_id', $run->id)->where('user_id', $bob->id)->value('credited_amount'), 0.01);

        // Commission at 10%: Alice 600, Bob 400.
        $this->assertEqualsWithDelta(600.0, (float) Reward::where('calc_run_id', $run->id)->where('user_id', $alice->id)->where('reward_type', RewardType::Commission->value)->value('computed_amount'), 0.01);
    }

    public function test_manager_earns_override_on_team_attainment(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);

        $manager = User::factory()->create(['name' => 'Mia Manager']);
        $rep = User::factory()->create(['name' => 'Rick Rep']);
        $ws->users()->attach($manager->id, ['role' => 'participant']);
        $ws->users()->attach($rep->id, ['role' => 'participant', 'manager_id' => $manager->id]);

        $plan = Plan::factory()->for($ws)->active()->create([
            'performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null,
            'manager_override_percent' => 5,
        ]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Rick', 'match_field' => 'rep', 'match_type' => 'exact']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 20000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Rick']]);

        $run = app(StartCalcRun::class)->handle($plan);

        // Manager override = 5% of the rep's 20,000 attainment = 1000.
        $override = Reward::where('calc_run_id', $run->id)
            ->where('user_id', $manager->id)
            ->where('reward_type', RewardType::Override->value)
            ->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $override->computed_amount, 0.01);
    }

    public function test_single_alias_still_credits_fully(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Solo']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Solo', 'match_field' => 'rep', 'match_type' => 'exact']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 5000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Solo']]);

        $run = app(StartCalcRun::class)->handle($plan);
        $this->assertEqualsWithDelta(5000.0, (float) Credit::where('calc_run_id', $run->id)->sum('credited_amount'), 0.01);
    }
}
