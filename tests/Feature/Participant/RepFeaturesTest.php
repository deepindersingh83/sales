<?php

namespace Tests\Feature\Participant;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Dispute;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_can_download_a_statement(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->actingAsMember($ws, Role::Participant);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 250, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        $response = $this->get(route('my.statement'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Payout', $response->streamedContent());
    }

    public function test_manager_sees_team_totals(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $manager = User::factory()->create(['name' => 'Mia']);
        $rep = User::factory()->create(['name' => 'Rick']);
        $ws->users()->attach($manager->id, ['role' => 'participant']);
        $ws->users()->attach($rep->id, ['role' => 'participant', 'manager_id' => $manager->id]);

        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        $tx = Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T', 'source_system' => 'csv', 'amount' => 1, 'currency' => 'USD', 'raw_data' => []]);
        Credit::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $rep->id, 'credited_amount' => 4000, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        $this->actingAs($manager)->withSession(['current_workspace_id' => $ws->id]);
        $this->get(route('my.team'))->assertOk()->assertSee('Rick')->assertSee('4,000');
    }

    public function test_rep_can_claim_a_transaction(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'D-99', 'source_system' => 'csv', 'amount' => 500, 'currency' => 'USD', 'raw_data' => []]);
        $rep = $this->actingAsMember($ws, Role::Participant);

        $this->post(route('disputes.claim'), ['external_id' => 'D-99', 'note' => 'mine'])->assertRedirect();

        $dispute = Dispute::where('user_id', $rep->id)->firstOrFail();
        $this->assertSame('Transaction claim', $dispute->category);
        $this->assertNotNull($dispute->transaction_id);
    }
}
