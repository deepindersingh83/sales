<?php

namespace Tests\Feature\Billing;

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionManager;
use App\Services\Billing\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_meter_counts_distinct_active_payees(): void
    {
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        $a = User::factory()->create();
        $b = User::factory()->create();

        // Two released rewards for A (counts once), one for B, one pending (ignored).
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $a->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 100, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $a->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 50, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $b->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 75, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $b->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 10, 'currency' => 'USD', 'status' => PayoutStatus::Pending]);

        $this->assertSame(2, app(UsageMeter::class)->activePayees($ws));
    }

    public function test_business_tier_charge_is_metered_per_payee(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'business']);
        $this->useWorkspace($ws);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);

        foreach (range(1, 3) as $i) {
            $u = User::factory()->create();
            Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $u->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 100, 'currency' => 'USD', 'status' => PayoutStatus::Released]);
        }

        $snapshot = app(UsageMeter::class)->snapshot($ws);
        $this->assertSame(3, $snapshot['active_payees']);
        $this->assertSame(3, $snapshot['billable']);
        $this->assertSame(90.0, $snapshot['charge']); // 3 * $30
    }

    public function test_free_tier_blocks_adding_members_beyond_cap(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'free']);
        $this->actingAsMember($ws, Role::FullAdmin); // 1 seat used

        // Fill to the cap of 3.
        $this->post(route('admin.members.store'), ['name' => 'Two', 'email' => 'two@t.test', 'role' => 'participant'])->assertRedirect();
        $this->post(route('admin.members.store'), ['name' => 'Three', 'email' => 'three@t.test', 'role' => 'participant'])->assertRedirect();

        $this->assertSame(3, $ws->users()->count());

        // Fourth is blocked.
        $this->from(route('admin.members.index'))
            ->post(route('admin.members.store'), ['name' => 'Four', 'email' => 'four@t.test', 'role' => 'participant'])
            ->assertSessionHasErrors('email');
        $this->assertSame(3, $ws->users()->count());
    }

    public function test_starting_a_trial_lifts_the_cap(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'free']);
        $this->useWorkspace($ws);

        $this->assertSame(3, $ws->payeeLimit());

        app(SubscriptionManager::class)->startTrial($ws);
        $ws->refresh();

        $this->assertTrue($ws->onTrial());
        $this->assertNull($ws->payeeLimit());
    }

    public function test_admin_can_upgrade_tier(): void
    {
        $ws = Workspace::factory()->create(['subscription_tier' => 'free']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->put(route('admin.billing.update'), ['tier' => 'business_plus'])->assertRedirect();

        $this->assertSame('business_plus', $ws->refresh()->tier());
    }

    public function test_billing_page_renders_usage(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.billing.index'))->assertOk()->assertSee('Active payees');
    }
}
