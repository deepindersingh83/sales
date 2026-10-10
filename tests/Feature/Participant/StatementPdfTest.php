<?php

namespace Tests\Feature\Participant;

use App\Enums\PayoutStatus;
use App\Enums\RewardType;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Reporting\MemberStatement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class StatementPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|null data handed to the PDF view */
    private ?array $statementData = null;

    private function captureStatementData(): void
    {
        View::composer('statements.pdf', function ($view): void {
            $this->statementData = $view->getData();
        });
    }

    private function runFor(Workspace $workspace, Plan $plan): CalcRun
    {
        return CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'status' => 'completed']);
    }

    private function credit(CalcRun $run, User $rep, string $reference, string $date, float $amount, PayoutStatus $status): Credit
    {
        $transaction = Transaction::create([
            'workspace_id' => $run->workspace_id,
            'external_id' => $reference,
            'source_system' => 'csv',
            'amount' => $amount,
            'currency' => 'USD',
            'transaction_date' => $date,
            'raw_data' => ['customer' => 'Acme Ltd'],
        ]);

        return Credit::create([
            'workspace_id' => $run->workspace_id,
            'calc_run_id' => $run->id,
            'transaction_id' => $transaction->id,
            'user_id' => $rep->id,
            'credited_amount' => $amount,
            'currency' => 'USD',
            'status' => $status,
        ]);
    }

    private function reward(CalcRun $run, User $rep, RewardType $type, float $amount, PayoutStatus $status, string $currency = 'USD'): Reward
    {
        return Reward::create([
            'workspace_id' => $run->workspace_id,
            'calc_run_id' => $run->id,
            'user_id' => $rep->id,
            'plan_id' => $run->plan_id,
            'reward_type' => $type,
            'computed_amount' => $amount,
            'currency' => $currency,
            'status' => $status,
        ]);
    }

    public function test_participant_downloads_their_own_statement_as_pdf(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);

        $response = $this->get(route('statements.pdf', $rep));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_participant_cannot_download_another_reps_statement(): void
    {
        $workspace = Workspace::factory()->create();
        $other = $this->makeMember($workspace, Role::Participant);
        $this->actingAsMember($workspace, Role::Participant);

        $this->get(route('statements.pdf', $other))->assertForbidden();
    }

    public function test_admin_downloads_a_members_statement(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->makeMember($workspace, Role::Participant);
        $this->actingAsMember($workspace, Role::FullAdmin);

        $this->get(route('statements.pdf', $rep))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_cannot_download_a_statement_for_someone_outside_the_workspace(): void
    {
        $outsider = $this->makeMember(Workspace::factory()->create(), Role::Participant);
        $this->actingAsMember(Workspace::factory()->create(), Role::FullAdmin);

        $this->get(route('statements.pdf', $outsider))->assertNotFound();
    }

    public function test_statement_contains_only_released_data_in_the_period(): void
    {
        $this->travelTo('2026-05-20 12:00:00');
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create(['name' => 'Core Plan']);
        $run = $this->runFor($workspace, $plan);

        $this->credit($run, $rep, 'INV-IN', '2026-05-03', 1000, PayoutStatus::Released);
        $this->credit($run, $rep, 'INV-PENDING', '2026-05-04', 500, PayoutStatus::Pending);
        $this->credit($run, $rep, 'INV-APRIL', '2026-04-28', 700, PayoutStatus::Released);
        $this->reward($run, $rep, RewardType::Commission, 100, PayoutStatus::Released);
        $this->reward($run, $rep, RewardType::Adjustment, -10, PayoutStatus::Released);
        $this->reward($run, $rep, RewardType::CashFixed, 999, PayoutStatus::Reviewed);
        $this->captureStatementData();

        $this->get(route('statements.pdf', ['member' => $rep, 'month' => '2026-05']))->assertOk();

        $this->assertSame(['INV-IN'], $this->statementData['credits']->map(fn (Credit $c) => $c->transaction->reference())->all());
        $this->assertEqualsCanonicalizing([RewardType::Commission, RewardType::Adjustment], $this->statementData['rewards']->pluck('reward_type')->all());
        $this->assertSame([[
            'plan' => 'Core Plan',
            'currency' => 'USD',
            'credited' => 1000.0,
            'commission' => 100.0,
            'other' => 0.0,
            'adjustments' => -10.0,
            'total' => 90.0,
        ]], $this->statementData['summary']->all());
    }

    public function test_limited_admin_statement_excludes_plans_hidden_from_them(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->makeMember($workspace, Role::Participant);
        $admin = $this->actingAsMember($workspace, Role::LimitedAdmin);
        $visible = Plan::factory()->for($workspace)->active()->create();
        $hidden = Plan::factory()->for($workspace)->active()->create();
        $hidden->hiddenFromUsers()->attach($admin->id);
        $this->reward($this->runFor($workspace, $visible), $rep, RewardType::Commission, 100, PayoutStatus::Released);
        $hiddenRun = $this->runFor($workspace, $hidden);
        $this->reward($hiddenRun, $rep, RewardType::Commission, 5000, PayoutStatus::Released);
        $this->credit($hiddenRun, $rep, 'INV-HIDDEN', now()->toDateString(), 50000, PayoutStatus::Released);
        $this->captureStatementData();

        $this->get(route('statements.pdf', $rep))->assertOk();

        $this->assertSame([$visible->id], $this->statementData['rewards']->pluck('plan_id')->all());
        $this->assertTrue($this->statementData['credits']->isEmpty());
    }

    public function test_calc_run_rewards_page_links_each_reps_statement_for_the_plan_period(): void
    {
        $this->travelTo('2026-02-10 09:00:00');
        $workspace = Workspace::factory()->create();
        $rep = $this->makeMember($workspace, Role::Participant);
        $this->actingAsMember($workspace, Role::FullAdmin);
        $plan = Plan::factory()->for($workspace)->active()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $run = $this->runFor($workspace, $plan);
        $this->reward($run, $rep, RewardType::Commission, 100, PayoutStatus::Pending);

        $this->get(route('admin.calc-runs.rewards.index', $run))
            ->assertOk()
            ->assertSee(route('statements.pdf', ['member' => $rep, 'from' => '2026-01-01', 'to' => '2026-02-10']));
    }

    public function test_totals_are_grouped_by_currency(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);
        $run = $this->runFor($workspace, Plan::factory()->for($workspace)->active()->create());
        $this->reward($run, $rep, RewardType::Commission, 100, PayoutStatus::Released, 'USD');
        $this->reward($run, $rep, RewardType::Commission, 40, PayoutStatus::Released, 'EUR');
        $this->captureStatementData();

        $this->get(route('statements.pdf', $rep))->assertOk();

        $this->assertEquals(['EUR' => 40.0, 'USD' => 100.0], $this->statementData['totals']->sortKeys()->all());
    }

    public function test_an_inverted_date_range_is_rejected(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);

        $this->get(route('statements.pdf', ['member' => $rep, 'from' => '2026-05-31', 'to' => '2026-05-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_statement_view_escapes_member_and_reward_text(): void
    {
        $workspace = Workspace::factory()->create();
        $this->useWorkspace($workspace);
        $rep = $this->makeMember($workspace, Role::Participant, ['name' => '<script>alert(1)</script>']);

        $data = app(MemberStatement::class)->build($rep, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth());
        $html = view('statements.pdf', $data + ['workspace' => $workspace])->render();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
