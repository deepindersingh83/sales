<?php

namespace Tests\Feature\Gamification;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Contest;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LeaderboardService;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_ranks_reps_by_released_credit(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        $tx = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T', 'source_system' => 'csv', 'amount' => 1, 'currency' => 'USD', 'raw_data' => []]);

        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $bob->id, 'credited_amount' => 900, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $alice->id, 'credited_amount' => 1500, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        $standings = app(LeaderboardService::class)->standings();
        $this->assertSame('Alice', $standings[0]['user']);
        $this->assertSame(1, $standings[0]['rank']);
        $this->assertSame('Bob', $standings[1]['user']);
    }

    public function test_contest_with_open_end_only_counts_deals_since_its_start(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $alice = User::factory()->create(['name' => 'Alice']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        foreach (['2020-01-15' => 500, '2026-10-05' => 300] as $date => $amount) {
            $tx = Transaction::create(['workspace_id' => $ws->id, 'external_id' => $date, 'source_system' => 'csv', 'amount' => $amount,
                'currency' => 'USD', 'transaction_date' => $date, 'raw_data' => []]);
            Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $alice->id,
                'credited_amount' => $amount, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        }
        $contest = Contest::create(['workspace_id' => $ws->id, 'name' => 'Q4', 'metric' => 'credited', 'starts_on' => '2026-10-01']);

        $standings = app(LeaderboardService::class)->standings($contest);

        $this->assertEqualsWithDelta(300.0, $standings[0]['total'], 0.001);
    }

    public function test_payout_contest_ranks_by_released_cash_rewards(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        $tx = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T', 'source_system' => 'csv', 'amount' => 1, 'currency' => 'USD', 'raw_data' => []]);
        // Alice has more credit, Bob earned more payout.
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $alice->id, 'credited_amount' => 5000, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $bob->id, 'plan_id' => $plan->id,
            'reward_type' => 'commission', 'computed_amount' => 700, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        $contest = Contest::create(['workspace_id' => $ws->id, 'name' => 'Top earner', 'metric' => 'payout']);

        $standings = app(LeaderboardService::class)->standings($contest);

        $this->assertSame('Bob', $standings[0]['user']);
        $this->assertEqualsWithDelta(700.0, $standings[0]['total'], 0.001);
        $this->assertCount(1, $standings);
    }

    public function test_admin_can_create_contest_and_participant_sees_leaderboard(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $this->post(route('admin.contests.store'), ['name' => 'Summer Sprint', 'metric' => 'credited'])->assertRedirect();

        $this->actingAsMember($ws, Role::Participant);
        $this->get(route('leaderboard.index'))->assertOk()->assertSee('Leaderboard');
    }

    public function test_participant_can_answer_a_survey(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $survey = Survey::create(['question' => 'How clear is your plan?', 'is_active' => true]);

        $rep = $this->actingAsMember($ws, Role::Participant);
        $this->post(route('surveys.respond', $survey), ['rating' => 4, 'comment' => 'Pretty clear'])->assertRedirect();

        $response = SurveyResponse::where('survey_id', $survey->id)->where('user_id', $rep->id)->firstOrFail();
        $this->assertSame(4, $response->rating);
    }
}
