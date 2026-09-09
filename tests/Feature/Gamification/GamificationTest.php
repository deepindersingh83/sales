<?php

namespace Tests\Feature\Gamification;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
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
