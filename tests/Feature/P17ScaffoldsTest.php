<?php

namespace Tests\Feature;

use App\Enums\PayoutStatus;
use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\CalcRun;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiAssistant;
use App\Services\Reporting\Asc606Report;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P17ScaffoldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_asc606_amortizes_released_commission_straight_line(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $rep = User::factory()->create();
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->create();
        $run = CalcRun::create(['workspace_id' => $ws->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        Reward::create(['workspace_id' => $ws->id, 'calc_run_id' => $run->id, 'user_id' => $rep->id, 'plan_id' => $plan->id, 'reward_type' => 'commission', 'computed_amount' => 1200, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        $schedule = app(Asc606Report::class)->schedule(12);

        $this->assertEqualsWithDelta(1200.0, $schedule['total'], 0.01);
        $this->assertCount(12, $schedule['by_month']);
        // 1200 / 12 = 100 recognized each month.
        $this->assertEqualsWithDelta(100.0, $schedule['by_month']->first(), 0.01);
    }

    public function test_participant_can_sign_and_enroll_in_a_plan(): void
    {
        $ws = Workspace::factory()->create();
        $rep = $this->actingAsMember($ws, Role::Participant);
        $plan = Plan::factory()->for($ws)->create(['status' => PlanStatus::Active]);

        $this->post(route('enrollments.sign', $plan), ['signature' => 'Alice Rep'])->assertRedirect();

        $enrollment = Enrollment::where('plan_id', $plan->id)->where('user_id', $rep->id)->firstOrFail();
        $this->assertSame('Alice Rep', $enrollment->signature);
        $this->assertNotNull($enrollment->signed_at);
        $this->assertSame($ws->id, $enrollment->workspace_id);
    }

    public function test_connectors_page_lists_live_and_scaffold_integrations(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.connectors.index'))
            ->assertOk()
            ->assertSee('Salesforce')
            ->assertSee('CSV Upload');
    }

    public function test_ai_assistant_is_a_stub_until_configured(): void
    {
        config(['services.ai.key' => null]);
        $ai = app(AiAssistant::class);

        $this->assertFalse($ai->isConfigured());
        $this->assertStringContainsString('not configured', $ai->answer('Why is my commission low?'));
    }
}
