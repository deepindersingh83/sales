<?php

namespace Tests\Feature\Calculation;

use App\Actions\SimulateCalc;
use App\Models\Alias;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_projects_payouts_without_persisting_anything(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 10000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        $result = app(SimulateCalc::class)->handle($plan, $rep->id);

        // Projected commission = 10% of 10,000 = 1000.
        $this->assertEqualsWithDelta(1000.0, $result['summary']['commission_total'], 0.01);
        $this->assertNotEmpty($result['rewards']);

        // Nothing persisted — the rollback discarded the run, version, credits, rewards.
        $this->assertSame(0, CalcRun::count());
        $this->assertSame(0, PlanVersion::count());
        $this->assertSame(0, Credit::count());
        $this->assertSame(0, Reward::count());
    }
}
