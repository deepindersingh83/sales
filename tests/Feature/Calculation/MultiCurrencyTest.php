<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\RewardType;
use App\Models\Alias;
use App\Models\Credit;
use App\Models\FxRate;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Calculation\FxConverter;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_converter_uses_workspace_rate_and_inverse(): void
    {
        $ws = Workspace::factory()->create();
        FxRate::create(['workspace_id' => $ws->id, 'base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => 1.10, 'effective_date' => '2026-01-01']);

        $fx = (new FxConverter)->forWorkspace($ws->id);

        $this->assertEqualsWithDelta(110.0, $fx->convert(100, 'EUR', 'USD', '2026-03-01'), 0.001);
        // Inverse derived.
        $this->assertEqualsWithDelta(100.0, $fx->convert(110, 'USD', 'EUR', '2026-03-01'), 0.001);
        // Same currency is 1:1.
        $this->assertEqualsWithDelta(50.0, $fx->convert(50, 'USD', 'USD'), 0.001);
    }

    public function test_engine_converts_transactions_to_plan_currency(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        FxRate::create(['workspace_id' => $ws->id, 'base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => 1.20, 'effective_date' => '2026-01-01']);

        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        // Plan pays in USD.
        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'currency' => 'USD', 'start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        // A EUR deal of 1000 -> 1200 USD credited.
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'E1', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'EUR', 'transaction_date' => '2026-03-01', 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);

        $credit = Credit::where('calc_run_id', $run->id)->firstOrFail();
        $this->assertSame('USD', $credit->currency);
        $this->assertEqualsWithDelta(1200.0, (float) $credit->credited_amount, 0.01);

        // Commission 10% of 1200 USD = 120.
        $this->assertEqualsWithDelta(120.0, (float) Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::Commission->value)->value('computed_amount'), 0.01);
    }
}
