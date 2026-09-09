<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalcControlsTest extends TestCase
{
    use RefreshDatabase;

    private function base(Workspace $ws, array $planAttrs = []): array
    {
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->active()->create(array_merge(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null], $planAttrs));
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        return [$plan, $rep];
    }

    public function test_excluded_transactions_are_not_credited(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'OK', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'EX', 'source_system' => 'csv', 'amount' => 5000, 'currency' => 'USD', 'excluded' => true, 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->count());
        $this->assertEqualsWithDelta(1000.0, (float) Credit::where('calc_run_id', $run->id)->sum('credited_amount'), 0.01);
    }

    public function test_pay_when_paid_skips_unpaid_transactions(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws, ['pay_when_paid' => true]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'PAID', 'source_system' => 'csv', 'amount' => 2000, 'currency' => 'USD', 'is_paid' => true, 'raw_data' => ['rep' => 'Alice']]);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'UNPAID', 'source_system' => 'csv', 'amount' => 8000, 'currency' => 'USD', 'is_paid' => false, 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);
        $this->assertEqualsWithDelta(2000.0, (float) Credit::where('calc_run_id', $run->id)->sum('credited_amount'), 0.01);
    }

    public function test_release_blocked_until_run_approved(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        $run = app(StartCalcRun::class)->handle($plan);

        $this->actingAsMember($ws, Role::FullAdmin);

        // Review is fine, but release is blocked before approval.
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'review']);
        $this->from(route('admin.calc-runs.credits.index', $run))
            ->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'release'])
            ->assertSessionHasErrors('approval');
        $this->assertSame(0, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Released)->count());

        // Approve, then release works.
        $this->post(route('admin.calc-runs.approve', $run))->assertRedirect();
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'release']);
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Released)->count());
    }
}
