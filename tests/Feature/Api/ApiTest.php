<?php

namespace Tests\Feature\Api;

use App\Enums\PayoutStatus;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_requires_a_valid_token(): void
    {
        $this->postJson('/api/v1/transactions', ['transactions' => []])->assertStatus(401);

        $this->withToken('nope')
            ->postJson('/api/v1/transactions', ['transactions' => []])
            ->assertStatus(401);
    }

    public function test_ingest_upserts_transactions_idempotently_and_is_tenant_scoped(): void
    {
        $ws = Workspace::factory()->create();
        $token = $ws->regenerateApiToken();

        $payload = ['source_system' => 'salesforce', 'transactions' => [
            ['external_id' => 'D-1', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']],
            ['external_id' => 'D-2', 'amount' => 2000],
        ]];

        $this->withToken($token)->postJson('/api/v1/transactions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('result.created', 2);

        // Re-ingest: idempotent, no duplicates.
        $this->withToken($token)->postJson('/api/v1/transactions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('result.created', 0)
            ->assertJsonPath('result.updated', 2);

        app(WorkspaceContext::class)->set($ws);
        $this->assertSame(2, Transaction::count());
        $this->assertSame('salesforce', Transaction::where('external_id', 'D-1')->first()->source_system);
    }

    public function test_payouts_feed_returns_released_rewards_only(): void
    {
        $ws = Workspace::factory()->create();
        $token = $ws->regenerateApiToken();
        app(WorkspaceContext::class)->set($ws);

        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 500, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 99, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);

        $this->withToken($token)->getJson('/api/v1/payouts')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', 500);
    }
}
