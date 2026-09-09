<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\RewardType;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DoublePaymentDetector;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_reversal_transaction_claws_back_commission(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        // A sale and a later reversal (claw-back) of part of it.
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'S1', 'source_system' => 'csv', 'amount' => 10000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'S1-REV', 'source_system' => 'csv', 'amount' => -4000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);

        // Net attainment 6000 -> 10% = 600.
        $commission = (float) Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::Commission->value)->value('computed_amount');
        $this->assertEqualsWithDelta(600.0, $commission, 0.01);
    }

    public function test_admin_can_add_a_manual_adjustment(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create();
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        $this->actingAsMember($ws, Role::FullAdmin);
        $this->post(route('admin.calc-runs.adjustments.store', $run), [
            'user_id' => $rep->id, 'amount' => -150.50, 'reason' => 'Overpaid last cycle',
        ])->assertRedirect();

        $adj = Reward::where('calc_run_id', $run->id)->where('reward_type', RewardType::Adjustment->value)->firstOrFail();
        $this->assertEqualsWithDelta(-150.50, (float) $adj->computed_amount, 0.01);
        $this->assertSame('Overpaid last cycle', $adj->meta['reason']);
    }

    public function test_double_payment_detection_flags_matching_amount_and_date(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);

        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'A', 'source_system' => 'crm', 'amount' => 500, 'transaction_date' => '2026-05-01', 'raw_data' => []]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'B', 'source_system' => 'erp', 'amount' => 500, 'transaction_date' => '2026-05-01', 'raw_data' => []]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'C', 'source_system' => 'crm', 'amount' => 999, 'transaction_date' => '2026-05-02', 'raw_data' => []]);

        $suspects = app(DoublePaymentDetector::class)->suspects();

        $this->assertCount(1, $suspects);
        $this->assertSame(2, $suspects[0]['count']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $suspects[0]['external_ids']);
    }
}
