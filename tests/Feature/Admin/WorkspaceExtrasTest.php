<?php

namespace Tests\Feature\Admin;

use App\Actions\StartCalcRun;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_admin_can_update_workspace_settings(): void
    {
        $ws = Workspace::factory()->create(['name' => 'Old', 'base_currency' => 'USD']);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->put(route('admin.settings.update'), ['name' => 'New Co', 'base_currency' => 'eur'])->assertRedirect();

        $ws->refresh();
        $this->assertSame('New Co', $ws->name);
        $this->assertSame('EUR', $ws->base_currency);
    }

    public function test_plan_admin_cannot_open_settings(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::PlanAdmin);

        $this->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_audit_trail_lists_calc_logs(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);

        $rep = User::factory()->create(['name' => 'Alice']);
        $ws->users()->attach($rep->id, ['role' => 'participant']);
        $plan = Plan::factory()->for($ws)->active()->create(['start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Alias::create(['workspace_id' => $ws->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'T1', 'source_system' => 'csv', 'amount' => 1000, 'currency' => 'USD', 'raw_data' => ['rep' => 'Alice']]);
        $run = app(StartCalcRun::class)->handle($plan);

        $this->actingAsMember($ws, Role::FullAdmin);
        $this->get(route('admin.calc-runs.logs', $run))
            ->assertOk()
            ->assertSee('credit_matched');
    }
}
