<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\CalcRunStatus;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Jobs\RunCalculation;
use App\Models\Alias;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
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

    public function test_pay_when_paid_counts_a_deal_in_the_period_its_cash_arrived(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws, ['pay_when_paid' => true, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        $deal = fn (string $id, string $invoiced, ?string $paid) => Transaction::create(['workspace_id' => $ws->id, 'external_id' => $id,
            'source_system' => 'xero', 'amount' => 1000, 'currency' => 'USD', 'is_paid' => true, 'transaction_date' => $invoiced,
            'paid_at' => $paid, 'raw_data' => ['rep' => 'Alice']]);

        $deal('AUG-INVOICED-SEP-PAID', '2026-08-20', '2026-09-05');  // counts in September
        $deal('SEP-INVOICED-OCT-PAID', '2026-09-10', '2026-10-02');  // belongs to October
        $deal('SEP-NO-PAID-DATE', '2026-09-15', null);               // falls back to invoice date

        $run = app(StartCalcRun::class)->handle($plan);

        $credited = Credit::where('calc_run_id', $run->id)->with('transaction')->get()->pluck('transaction.external_id')->sort()->values()->all();
        $this->assertSame(['AUG-INVOICED-SEP-PAID', 'SEP-NO-PAID-DATE'], $credited);
    }

    public function test_plans_without_pay_when_paid_still_use_the_invoice_date(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws, ['start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'AUG', 'source_system' => 'xero', 'amount' => 1000,
            'currency' => 'USD', 'is_paid' => true, 'transaction_date' => '2026-08-20', 'paid_at' => '2026-09-05', 'raw_data' => ['rep' => 'Alice']]);

        $run = app(StartCalcRun::class)->handle($plan);

        $this->assertSame(0, Credit::where('calc_run_id', $run->id)->count());
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

    public function test_only_a_completed_unapproved_run_can_be_approved(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws);
        $running = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => CalcRunStatus::Running]);
        $approvedAt = now()->subDay()->startOfSecond();
        $approved = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => CalcRunStatus::Completed, 'approved_at' => $approvedAt]);

        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.calc-runs.approve', $running))->assertSessionHasErrors('approval');
        $this->assertNull($running->fresh()->approved_at);

        $this->post(route('admin.calc-runs.approve', $approved))->assertSessionHasErrors('approval');
        $this->assertTrue($approved->fresh()->approved_at->equalTo($approvedAt));
    }

    public function test_adding_an_adjustment_revokes_the_run_approval(): void
    {
        $ws = Workspace::factory()->create();
        [$plan, $rep] = $this->base($ws);
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => CalcRunStatus::Completed]);

        $admin = $this->actingAsMember($ws, Role::FullAdmin);
        $run->update(['approved_at' => now(), 'approved_by_user_id' => $admin->id]);

        $this->post(route('admin.calc-runs.adjustments.store', $run), [
            'user_id' => $rep->id, 'amount' => 250, 'reason' => 'Late deal',
        ])->assertRedirect();

        $run->refresh();
        $this->assertNull($run->approved_at);
        $this->assertNull($run->approved_by_user_id);
    }

    public function test_a_redelivered_calculation_job_does_not_run_twice(): void
    {
        $ws = Workspace::factory()->create();
        [$plan] = $this->base($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        $run = app(StartCalcRun::class)->handle($plan);

        // The queue re-delivers the job while one worker is still running it, and again after it finished.
        $run->update(['status' => CalcRunStatus::Running]);
        RunCalculation::dispatchSync($run->id);
        $run->update(['status' => CalcRunStatus::Completed]);
        RunCalculation::dispatchSync($run->id);

        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->count());
        $this->assertSame(1, Reward::where('calc_run_id', $run->id)->count());
        $this->assertSame(CalcRunStatus::Completed, $run->fresh()->status);
    }

    public function test_database_queue_does_not_redeliver_a_calculation_before_it_times_out(): void
    {
        $this->assertGreaterThan((new RunCalculation(0))->timeout, config('queue.connections.database.retry_after'));
    }
}
