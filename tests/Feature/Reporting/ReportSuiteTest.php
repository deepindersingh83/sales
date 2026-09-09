<?php

namespace Tests\Feature\Reporting;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Reporting\ReportBuilder;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSuiteTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(Workspace $ws): User
    {
        app(WorkspaceContext::class)->set($ws);
        $alice = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($alice->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        $tx = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 1300, 'currency' => 'USD', 'raw_data' => []]);

        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $alice->id, 'credited_amount' => 1300, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $alice->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 900, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        // Pending reward = accrued liability, excluded from released reports.
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $alice->id, 'plan_id' => $plan->id, 'reward_type' => 'cash_fixed', 'computed_amount' => 1000, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);

        return $alice;
    }

    public function test_attainment_liability_and_type_reports(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedData($ws);
        $reports = app(ReportBuilder::class);

        $attainment = $reports->attainmentByUser();
        $this->assertEqualsWithDelta(1300.0, $attainment[0]['credited'], 0.01);
        $this->assertEqualsWithDelta(900.0, $attainment[0]['payout'], 0.01);

        $liability = $reports->liabilityByUser();
        $this->assertEqualsWithDelta(1000.0, $liability[0]['liability'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $reports->totalLiability(), 0.01);

        $byType = $reports->payoutByType();
        $this->assertSame('Commission', $byType[0]['type']);
        $this->assertEqualsWithDelta(900.0, $byType[0]['total'], 0.01); // pending cash_fixed excluded
    }

    public function test_report_pages_render_for_admin(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedData($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.reports.overview'))->assertOk()->assertSee('Analytics');
        $this->get(route('admin.reports.attainment'))->assertOk()->assertSee('Alice');
        $this->get(route('admin.reports.liability'))->assertOk();
        $this->get(route('admin.reports.attainment', ['export' => 'csv']))->assertOk();
    }
}
