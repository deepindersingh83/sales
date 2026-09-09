<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\RewardType;
use App\Models\Alias;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedMechanicsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlan(Workspace $ws, array $planAttrs): Plan
    {
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create(array_merge([
            'performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null,
        ], $planAttrs));
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);
        // Total revenue 15,000.
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 15000, 'profit_amount' => 6000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        return $plan;
    }

    private function commissionFor(Plan $plan): float
    {
        $run = app(StartCalcRun::class)->handle($plan);

        return (float) Reward::where('calc_run_id', $run->id)
            ->where('reward_type', RewardType::Commission->value)
            ->value('computed_amount');
    }

    public function test_custom_formula_replaces_tier_math(): void
    {
        $ws = Workspace::factory()->create();
        $plan = $this->seedPlan($ws, [
            'quota' => 10000,
            'commission_formula' => 'revenue * 0.05 + max(0, attainment_pct - 1) * 2000',
        ]);
        // Give it a tier too, to prove the formula wins.
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.99, 'is_cumulative' => true, 'sort_order' => 0]);

        // 15000*0.05 = 750, plus (1.5 - 1)*2000 = 1000 -> 1750 (not the 0.99 tier).
        $this->assertEqualsWithDelta(1750.0, $this->commissionFor($plan), 0.01);
    }

    public function test_payout_cap_limits_commission(): void
    {
        $ws = Workspace::factory()->create();
        $plan = $this->seedPlan($ws, ['payout_cap' => 500]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);

        // 15000 * 10% = 1500, capped to 500.
        $this->assertEqualsWithDelta(500.0, $this->commissionFor($plan), 0.01);
    }

    public function test_attainment_pct_recorded_on_reward(): void
    {
        $ws = Workspace::factory()->create();
        $plan = $this->seedPlan($ws, ['quota' => 10000]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => true, 'sort_order' => 0]);

        $run = app(StartCalcRun::class)->handle($plan);
        $reward = Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::Commission->value)->firstOrFail();

        // 15000 / 10000 = 1.5.
        $this->assertEqualsWithDelta(1.5, $reward->meta['attainment_pct'], 0.001);
    }
}
