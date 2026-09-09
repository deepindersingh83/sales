<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\PayoutStatus;
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

class PlanDesignGapsTest extends TestCase
{
    use RefreshDatabase;

    private function baseSetup(Workspace $ws, array $planAttrs = []): array
    {
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->active()->create(array_merge([
            'performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null,
        ], $planAttrs));
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        return [$plan, $rep];
    }

    private function commission(int $runId): float
    {
        return (float) Reward::where('calc_run_id', $runId)->where('reward_type', RewardType::Commission->value)->value('computed_amount');
    }

    public function test_exclude_tax_nets_down_the_basis(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->baseSetup($ws, ['tax_rate_percent' => 20]); // 20% tax excluded
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 10000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);
        // Net basis 8000 -> credit 8000, commission 10% = 800.
        $this->assertEqualsWithDelta(8000.0, (float) Credit::where('calc_run_id', $run->id)->sum('credited_amount'), 0.01);
        $this->assertEqualsWithDelta(800.0, $this->commission($run->id), 0.01);
    }

    public function test_transaction_filter_limits_scope(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->baseSetup($ws, ['filter_field' => 'region', 'filter_value' => 'North']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'N', 'source_system' => 'csv', 'amount' => 5000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice', 'region' => 'North']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'S', 'source_system' => 'csv', 'amount' => 9999, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice', 'region' => 'South']]);

        $run = app(StartCalcRun::class)->handle($plan);
        // Only the North deal (5000) counts.
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->count());
        $this->assertEqualsWithDelta(500.0, $this->commission($run->id), 0.01);
    }

    public function test_salary_based_allocation(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->baseSetup($ws);
        $ws->users()->updateExistingPivot($rep->id, ['salary' => 100000]);
        // 0.5% of salary reward.
        $plan->rewardRules()->create(['reward_type' => RewardType::CashPctSalary->value, 'value' => 0.005]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);
        $salaryReward = Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::CashPctSalary->value)->firstOrFail();
        $this->assertEqualsWithDelta(500.0, (float) $salaryReward->computed_amount, 0.01); // 0.5% of 100k
    }

    public function test_true_up_pays_only_the_delta(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->baseSetup($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 10000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        // First run: gross 1000, release it.
        $run1 = app(StartCalcRun::class)->handle($plan);
        Reward::where('calc_run_id', $run1->id)->update(['status' => PayoutStatus::Released]);
        $this->assertEqualsWithDelta(1000.0, $this->commission($run1->id), 0.01);

        // Add another deal, then true-up: new gross 1500, already paid 1000 -> delta 500.
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T2', 'source_system' => 'csv', 'amount' => 5000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        $run2 = app(StartCalcRun::class)->handle($plan, null, false, 'true_up');
        $this->assertEqualsWithDelta(500.0, $this->commission($run2->id), 0.01);
    }
}
