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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedReportData(Workspace $ws): array
    {
        app(WorkspaceContext::class)->set($ws);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $ws->users()->attach($alice->id, ['role' => 'participant']);
        $ws->users()->attach($bob->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->create(['name' => 'Main Plan']);
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        // Released rewards.
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $alice->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 900, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $bob->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 500, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        // Pending reward — must be excluded from reports.
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $alice->id, 'plan_id' => $plan->id, 'reward_type' => 'cash_fixed', 'computed_amount' => 1000, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);

        // Released credits with a product field.
        $tx1 = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'A', 'source_system' => 'csv', 'amount' => 600, 'currency' => 'USD', 'raw_data' => ['product' => 'Widget']]);
        $tx2 = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'B', 'source_system' => 'csv', 'amount' => 400, 'currency' => 'USD', 'raw_data' => ['product' => 'Widget']]);
        $tx3 = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'C', 'source_system' => 'csv', 'amount' => 300, 'currency' => 'USD', 'raw_data' => ['product' => 'Gadget']]);
        foreach ([[$tx1, 600], [$tx2, 400], [$tx3, 300]] as [$tx, $amt]) {
            Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $alice->id, 'credited_amount' => $amt, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        }

        return [$plan, $alice, $bob];
    }

    public function test_payout_by_user_sums_released_rewards_only(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedReportData($ws);

        $rows = app(ReportBuilder::class)->payoutByUser();

        $this->assertSame('Alice', $rows[0]['user']);   // sorted desc
        $this->assertEqualsWithDelta(900.0, $rows[0]['total'], 0.01);   // pending 1000 excluded
        $this->assertSame('Bob', $rows[1]['user']);
        $this->assertEqualsWithDelta(500.0, $rows[1]['total'], 0.01);
    }

    public function test_payout_by_plan_totals_released_rewards(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedReportData($ws);

        $rows = app(ReportBuilder::class)->payoutByPlan();
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1400.0, $rows[0]['total'], 0.01);
    }

    public function test_crediting_grouped_by_product(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedReportData($ws);

        $rows = app(ReportBuilder::class)->creditingByField('product');
        $byKey = $rows->keyBy('key');

        $this->assertEqualsWithDelta(1000.0, $byKey['Widget']['total'], 0.01);
        $this->assertSame(2, $byKey['Widget']['count']);
        $this->assertEqualsWithDelta(300.0, $byKey['Gadget']['total'], 0.01);
    }

    public function test_csv_export_streams_text_csv(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedReportData($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $response = $this->get(route('admin.reports.payout-by-user', ['export' => 'csv']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('User,Total,Currency', $body);
        $this->assertStringContainsString('Alice', $body);
    }

    public function test_reports_visible_to_admins_but_not_participants(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedReportData($ws);

        $this->actingAsMember($ws, Role::LimitedAdmin);
        $this->get(route('admin.reports.index'))->assertOk();

        $this->actingAsMember($ws, Role::Participant);
        $this->get(route('admin.reports.index'))->assertForbidden();
    }
}
