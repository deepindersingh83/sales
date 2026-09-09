<?php

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The spec's headline requirement: workspace A can never see workspace B's data
 * — enforced at the ORM layer by the global WorkspaceScope, not application code.
 */
class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_only_return_current_workspace_rows(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();

        $planA = Plan::factory()->for($a)->create(['name' => 'Plan A']);
        $planB = Plan::factory()->for($b)->create(['name' => 'Plan B']);

        $this->useWorkspace($a);

        $ids = Plan::query()->pluck('id')->all();

        $this->assertContains($planA->id, $ids);
        $this->assertNotContains($planB->id, $ids, 'Workspace A leaked a Workspace B plan.');
    }

    public function test_cannot_fetch_another_workspaces_record_by_id(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $planB = Plan::factory()->for($b)->create();

        $this->useWorkspace($a);

        // Even a direct find() by primary key must be scoped away.
        $this->assertNull(Plan::find($planB->id));
    }

    public function test_creating_a_model_stamps_the_current_workspace(): void
    {
        $a = Workspace::factory()->create();
        $this->useWorkspace($a);

        $tx = Transaction::create([
            'external_id' => 'X-1',
            'source_system' => 'csv',
            'amount' => 100,
        ]);

        $this->assertSame($a->id, $tx->workspace_id);
    }

    public function test_no_workspace_context_returns_no_tenant_rows(): void
    {
        $a = Workspace::factory()->create();
        Plan::factory()->for($a)->create();

        // Guest / unresolved context: the scope must return nothing rather than
        // leaking every tenant's rows.
        app(WorkspaceContext::class)->clear();

        $this->assertCount(0, Plan::all());
    }

    public function test_explicit_opt_out_can_cross_tenants_for_trusted_code(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        Plan::factory()->for($a)->create();
        Plan::factory()->for($b)->create();

        $this->useWorkspace($a);

        // The documented escape hatch for console/admin operations.
        $this->assertSame(2, Plan::acrossAllWorkspaces()->count());
    }
}
