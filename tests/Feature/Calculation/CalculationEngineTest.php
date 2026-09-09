<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\CalcRunStatus;
use App\Enums\RewardType;
use App\Models\Alias;
use App\Models\CalcLog;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlanWithData(Workspace $ws): array
    {
        app(WorkspaceContext::class)->set($ws);

        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create([
            'performance_metric' => 'revenue',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        // 5% to 10k, 8% above (cumulative).
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => 10000, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => true, 'sort_order' => 0]);
        $plan->tiers()->create(['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.08, 'is_cumulative' => true, 'sort_order' => 1]);
        $plan->rewardRules()->create(['reward_type' => RewardType::CashFixed->value, 'value' => 100]);

        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        // Two credited deals (total revenue 15k) + one for a different rep (no alias).
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 6000, 'profit_amount' => 2000, 'currency' => 'USD', 'transaction_date' => '2026-03-01', 'raw_data' => ['rep' => 'Alice']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T2', 'source_system' => 'csv', 'amount' => 9000, 'profit_amount' => 3000, 'currency' => 'USD', 'transaction_date' => '2026-04-01', 'raw_data' => ['rep' => 'Alice']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T3', 'source_system' => 'csv', 'amount' => 5000, 'profit_amount' => 1000, 'currency' => 'USD', 'transaction_date' => '2026-05-01', 'raw_data' => ['rep' => 'Nobody']]);

        return [$plan, $rep];
    }

    public function test_run_credits_transactions_and_computes_tiered_commission(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->seedPlanWithData($ws);

        $run = app(StartCalcRun::class)->handle($plan, $rep->id);
        $run->refresh();

        $this->assertSame(CalcRunStatus::Completed, $run->status);

        // Two transactions credited to Alice; the unmatched one is not.
        $this->assertSame(2, Credit::where('calc_run_id', $run->id)->count());
        $this->assertSame(15000.0, (float) Credit::where('calc_run_id', $run->id)->sum('credited_amount'));

        // Commission on 15k revenue: 10k*5% + 5k*8% = 900.
        $commission = Reward::where('calc_run_id', $run->id)
            ->where('reward_type', RewardType::Commission->value)->firstOrFail();
        $this->assertEqualsWithDelta(900.0, (float) $commission->computed_amount, 0.01);
        $this->assertSame($rep->id, $commission->user_id);

        // Fixed reward rule applied once for the credited rep.
        $fixed = Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::CashFixed->value)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $fixed->computed_amount, 0.01);

        // Everything starts pending (nothing released yet).
        $this->assertSame(0, Credit::where('calc_run_id', $run->id)->where('status', '!=', 'pending')->count());
    }

    public function test_every_step_is_logged_for_audit(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->seedPlanWithData($ws);

        $run = app(StartCalcRun::class)->handle($plan, $rep->id);

        $logs = CalcLog::where('calc_run_id', $run->id)->get();
        $this->assertGreaterThanOrEqual(3, $logs->count());
        $this->assertTrue($logs->contains('step', 'credit_matched'));
        $this->assertTrue($logs->contains('step', 'uncredited'));   // T3 had no alias
        $this->assertTrue($logs->contains('step', 'tier_applied'));

        // The tier_applied log carries the audit breakdown.
        $tierLog = $logs->firstWhere('step', 'tier_applied');
        $this->assertArrayHasKey('breakdown', $tierLog->context);
    }

    public function test_snapshot_makes_runs_reproducible_after_plan_edits(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->seedPlanWithData($ws);

        $run1 = app(StartCalcRun::class)->handle($plan, $rep->id);
        $version1Snapshot = $run1->planVersion->snapshot;

        // Mutate the live plan: wipe tiers, add a totally different one.
        $plan->tiers()->delete();
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.20, 'is_cumulative' => true, 'sort_order' => 0]);

        // The first run's snapshot is untouched.
        $run1->refresh();
        $this->assertEquals($version1Snapshot, $run1->planVersion->snapshot);
        $this->assertCount(2, $run1->planVersion->snapshot['tiers']);

        // A new run snapshots the edited plan as version 2.
        $run2 = app(StartCalcRun::class)->handle($plan, $rep->id);
        $this->assertSame(2, $run2->planVersion->version_number);
        $this->assertCount(1, $run2->planVersion->snapshot['tiers']);

        // New math: 15k * 20% = 3000.
        $commission = Reward::where('calc_run_id', $run2->id)->where('reward_type', RewardType::Commission->value)->firstOrFail();
        $this->assertEqualsWithDelta(3000.0, (float) $commission->computed_amount, 0.01);
    }

    public function test_run_only_credits_its_own_workspace(): void
    {
        $wsA = Workspace::factory()->create();
        [$planA, $repA] = $this->seedPlanWithData($wsA);

        // A second workspace with its own transactions matching "Alice" too.
        $wsB = Workspace::factory()->create();
        $this->seedPlanWithData($wsB);

        app(WorkspaceContext::class)->set($wsA);
        $run = app(StartCalcRun::class)->handle($planA, $repA->id);

        // Only workspace A's two credited transactions count.
        $this->assertSame(2, Credit::where('calc_run_id', $run->id)->count());
    }
}
