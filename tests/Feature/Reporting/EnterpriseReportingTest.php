<?php

namespace Tests\Feature\Reporting;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\FxRate;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Reporting\ReportBuilder;
use App\Services\Reporting\RevenueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnterpriseReportingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alice (reports to Maya) on a 2,000-quota plan: two released credits of
     * 1,000 and 500 (75% attainment). Bob has no credits. One transaction is
     * uncredited, one is excluded, one is in EUR.
     *
     * @return array{Workspace, User, User, Plan}
     */
    private function seedScenario(): array
    {
        $ws = Workspace::factory()->create(['base_currency' => 'USD']);
        $this->useWorkspace($ws);

        $maya = User::factory()->create(['name' => 'Maya']);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $ws->users()->attach($maya->id, ['role' => 'full_admin']);
        $ws->users()->attach($alice->id, ['role' => 'participant', 'manager_id' => $maya->id]);
        $ws->users()->attach($bob->id, ['role' => 'participant', 'manager_id' => $maya->id]);

        $plan = Plan::factory()->for($ws)->create(['name' => 'Enterprise AE', 'quota' => 2000]);
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        $tx = fn (string $id, float $amount, string $date, array $extra = []) => Transaction::create(array_merge([
            'workspace_id' => $ws->id, 'external_id' => $id, 'source_system' => 'xero', 'amount' => $amount,
            'currency' => 'USD', 'transaction_date' => $date, 'is_paid' => false, 'raw_data' => ['customer' => 'Acme', 'product' => 'PRO'],
        ], $extra));

        $a = $tx('A', 1000, '2026-07-10', ['profit_amount' => 400, 'is_paid' => true]);
        $b = $tx('B', 500, '2026-08-05', ['profit_amount' => 100]);
        $tx('C', 200, '2026-08-20', ['raw_data' => ['customer' => 'Globex', 'product' => 'LITE']]); // uncredited
        $tx('D', 9999, '2026-08-21', ['excluded' => true]);
        $tx('E', 100, '2026-08-22', ['currency' => 'EUR', 'raw_data' => ['customer' => 'Initech']]);
        FxRate::create(['workspace_id' => $ws->id, 'base_currency' => 'EUR', 'quote_currency' => 'USD', 'rate' => 1.5, 'effective_date' => '2026-01-01']);

        foreach ([[$a, 1000], [$b, 500]] as [$t, $amount]) {
            Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $t->id, 'user_id' => $alice->id, 'credited_amount' => $amount, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        }
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $alice->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 150, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        return [$ws, $alice, $bob, $plan];
    }

    public function test_revenue_kpis_convert_currency_and_ignore_excluded(): void
    {
        $this->seedScenario();

        $kpis = app(RevenueAnalytics::class)->kpis();

        // 1000 + 500 + 200 + (100 EUR × 1.5); excluded 9999 ignored.
        $this->assertEqualsWithDelta(1850.0, $kpis['revenue'], 0.01);
        $this->assertSame(4, $kpis['deals']);
        $this->assertSame(3, $kpis['customers']);
        $this->assertEqualsWithDelta(33.3, $kpis['margin'], 0.05); // 500 profit on the 1,500 that reports profit
    }

    public function test_revenue_growth_compares_with_the_preceding_period(): void
    {
        $this->seedScenario();

        $kpis = app(RevenueAnalytics::class)->kpis(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        // August 850 vs. the 31 days before (July 1–31) 1000.
        $this->assertEqualsWithDelta(850.0, $kpis['revenue'], 0.01);
        $this->assertEqualsWithDelta(-15.0, $kpis['growth'], 0.05);
    }

    public function test_revenue_breakdowns_by_time_customer_rep_and_payment(): void
    {
        $this->seedScenario();
        $revenue = app(RevenueAnalytics::class);

        $months = $revenue->breakdown('month');
        $this->assertSame(['2026-07', '2026-08'], $months->pluck('key')->all());
        $this->assertEqualsWithDelta(850.0, $months[1]['revenue'], 0.01);

        $this->assertSame('Acme', $revenue->breakdown('customer')[0]['key']);

        $reps = $revenue->breakdown('rep')->keyBy('key');
        $this->assertEqualsWithDelta(1500.0, $reps['Alice']['revenue'], 0.01);
        $this->assertEqualsWithDelta(350.0, $reps['Uncredited']['revenue'], 0.01);

        $payment = $revenue->breakdown('payment')->keyBy('key');
        $this->assertEqualsWithDelta(1000.0, $payment['Paid']['revenue'], 0.01);
    }

    public function test_quota_attainment_team_rollup_and_distribution(): void
    {
        $this->seedScenario();
        $reports = app(ReportBuilder::class);

        $quota = $reports->quotaAttainment();
        $this->assertCount(1, $quota);
        $this->assertSame('Alice', $quota[0]['user']);
        $this->assertEqualsWithDelta(75.0, $quota[0]['attainment'], 0.01);

        $team = $reports->attainmentByManager();
        $this->assertSame('Maya', $team[0]['manager']);
        $this->assertSame(2, $team[0]['reps']);
        $this->assertEqualsWithDelta(1500.0, $team[0]['credited'], 0.01);

        $bands = $reports->attainmentDistribution()->keyBy('band');
        $this->assertSame(1, $bands['75–100%']['count']);
        $this->assertSame(0, $bands['< 50%']['count']);

        $this->assertEqualsWithDelta(75.0, $reports->attainmentByPlan()[0]['avg_attainment'], 0.01);
    }

    public function test_crediting_by_rep_and_uncredited_transactions(): void
    {
        $this->seedScenario();
        $reports = app(ReportBuilder::class);

        $byRep = $reports->creditingBy('rep');
        $this->assertSame('Alice', $byRep[0]['key']);
        $this->assertEqualsWithDelta(1500.0, $byRep[0]['total'], 0.01);

        // Excluded D is not an exception; C and E are.
        $this->assertSame(['E', 'C'], $reports->uncreditedTransactions()->pluck('external_id')->all());
    }

    public function test_report_pages_render_and_export(): void
    {
        [$ws] = $this->seedScenario();
        $this->actingAsMember($ws, Role::FullAdmin);

        foreach (['overview', 'revenue', 'quota-attainment', 'attainment-by-plan', 'attainment-by-team',
            'attainment-distribution', 'payout-by-type', 'payout-by-month', 'uncredited', 'crediting'] as $report) {
            $this->get(route("admin.reports.{$report}"))->assertOk();
        }

        $this->get(route('admin.reports.revenue', ['dimension' => 'customer', 'from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()->assertSee('Globex');
        $this->get(route('admin.reports.crediting', ['field' => 'rep']))->assertOk()->assertSee('Alice');

        $csv = $this->get(route('admin.reports.quota-attainment', ['export' => 'csv']))->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Alice,"Enterprise AE",1500,2000,75', $csv);
    }

    public function test_revenue_rejects_an_unknown_dimension_or_inverted_range(): void
    {
        [$ws] = $this->seedScenario();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.reports.revenue', ['dimension' => 'password']))->assertSessionHasErrors('dimension');
        $this->get(route('admin.reports.revenue', ['from' => '2026-09-01', 'to' => '2026-08-01']))->assertSessionHasErrors('to');
    }

    public function test_participants_cannot_open_reports(): void
    {
        [$ws] = $this->seedScenario();
        $this->actingAsMember($ws, Role::Participant);

        $this->get(route('admin.reports.revenue'))->assertForbidden();
    }
}
