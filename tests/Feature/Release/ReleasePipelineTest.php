<?php

namespace Tests\Feature\Release;

use App\Actions\StartCalcRun;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function completedRun(Workspace $ws, ?User $trigger = null): CalcRun
    {
        app(WorkspaceContext::class)->set($ws);

        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);

        $plan = Plan::factory()->for($ws)->active()->create(['performance_metric' => 'revenue', 'start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'transaction_date' => '2026-03-01', 'raw_data' => ['rep' => 'Alice']]);

        return app(StartCalcRun::class)->handle($plan, $trigger?->id);
    }

    public function test_credits_progress_pending_to_reviewed_to_released(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        $admin = $this->actingAsMember($ws, Role::FullAdmin);

        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Pending)->count());

        // Review all pending.
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'review'])->assertRedirect();
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Reviewed)->count());

        // Release all reviewed.
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'release'])->assertRedirect();
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Released)->count());
    }

    public function test_release_skips_items_not_yet_reviewed(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        // Releasing while still pending is a no-op (must be reviewed first).
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'release']);
        $this->assertSame(0, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Released)->count());
    }

    public function test_revert_steps_back_one_stage(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'review']);
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'revert']);
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Pending)->count());
    }

    public function test_release_gate_only_released_is_rep_visible(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        app(WorkspaceContext::class)->set($ws);

        // Nothing released yet -> the rep-facing scope returns nothing.
        $this->assertSame(0, Credit::released()->count());

        // After the full pipeline, it becomes visible.
        Credit::where('calc_run_id', $run->id)->update(['status' => PayoutStatus::Released]);
        $this->assertSame(1, Credit::released()->count());
    }

    public function test_limited_admin_cannot_release(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        $this->actingAsMember($ws, Role::LimitedAdmin);

        // Read-only role can view but not transition.
        $this->get(route('admin.calc-runs.credits.index', $run))->assertOk();
        $this->post(route('admin.calc-runs.credits.transition', $run), ['action' => 'review'])->assertForbidden();
        $this->assertSame(1, Credit::where('calc_run_id', $run->id)->where('status', PayoutStatus::Pending)->count());
    }

    public function test_unassigned_plan_admin_cannot_release(): void
    {
        $ws = Workspace::factory()->create();
        $run = $this->completedRun($ws);
        $this->actingAsMember($ws, Role::PlanAdmin); // not assigned to the plan

        $this->post(route('admin.calc-runs.rewards.transition', $run), ['action' => 'review'])->assertForbidden();
    }
}
