<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedSearchable(Workspace $ws): Plan
    {
        $this->useWorkspace($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'INV-SECRET', 'source_system' => 'csv',
            'amount' => 98765, 'currency' => 'USD', 'raw_data' => []]);

        return Plan::factory()->for($ws)->create(['name' => 'INV Gold Plan']);
    }

    public function test_participants_do_not_see_transactions_or_plans(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedSearchable($ws);
        $this->actingAsMember($ws, Role::Participant);

        $this->get(route('search.index', ['q' => 'INV']))
            ->assertOk()
            ->assertDontSee('INV-SECRET')
            ->assertDontSee('98,765')
            ->assertDontSee('INV Gold Plan');
    }

    public function test_admins_find_plans_they_may_view_but_not_hidden_ones(): void
    {
        $ws = Workspace::factory()->create();
        $plan = $this->seedSearchable($ws);
        Plan::factory()->for($ws)->create(['name' => 'INV Silver Plan']);
        $limited = $this->actingAsMember($ws, Role::LimitedAdmin);
        $plan->hiddenFromUsers()->attach($limited->id);

        $this->get(route('search.index', ['q' => 'INV']))
            ->assertOk()
            ->assertSee('INV-SECRET')
            ->assertSee('INV Silver Plan')
            ->assertDontSee('INV Gold Plan');
    }

    public function test_array_search_terms_do_not_crash_pages(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get('/search?q[]=x')->assertOk();
        $this->get('/dashboard?q[]=x')->assertOk();
        $this->get('/admin/products?q[]=x')->assertOk();
    }
}
