<?php

namespace Tests\Feature\Admin;

use App\Actions\StartCalcRun;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Alias;
use App\Models\Credit;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListTest extends TestCase
{
    use RefreshDatabase;

    private function customer(Workspace $ws, string $contactId, string $name, array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'workspace_id' => $ws->id, 'source_system' => 'xero', 'external_id' => $contactId, 'name' => $name,
        ], $attrs));
    }

    public function test_customers_list_with_totals_search_and_filters(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $rep = $this->makeMember($ws, Role::Participant, ['name' => 'Alice']);
        $this->customer($ws, 'C-ACME', 'Acme Pty Ltd', ['email' => 'ap@acme.test', 'owner_user_id' => $rep->id]);
        $this->customer($ws, 'C-GLOBEX', 'Globex');
        $this->customer($ws, 'C-OLD', 'Old Co', ['status' => 'archived']);
        Transaction::factory()->for($ws)->create(['external_id' => 'I1', 'amount' => 1000, 'currency' => 'AUD', 'is_paid' => false,
            'raw_data' => ['contact_id' => 'C-ACME', 'customer' => 'Acme Pty Ltd', 'total' => 1100, 'amount_due' => 550]]);

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Acme Pty Ltd')->assertSee('Globex')->assertDontSee('Old Co')
            ->assertSee('AUD 1,000.00')->assertSee('AUD 500.00');

        $this->get(route('admin.customers.index', ['q' => 'acme.test']))->assertSee('Acme Pty Ltd')->assertDontSee('Globex');
        $this->get(route('admin.customers.index', ['owner' => 'none']))->assertSee('Globex')->assertDontSee('Acme Pty Ltd');
        $this->get(route('admin.customers.index', ['owner' => (string) $rep->id]))->assertSee('Acme Pty Ltd')->assertDontSee('Globex');
        $this->get(route('admin.customers.index', ['status' => 'archived']))->assertSee('Old Co')->assertDontSee('Globex');
    }

    public function test_assigning_a_rep_credits_them_with_the_customers_invoices(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $alice = $this->makeMember($ws, Role::Participant, ['name' => 'Alice']);
        $bob = $this->makeMember($ws, Role::Participant, ['name' => 'Bob']);
        $acme = $this->customer($ws, 'C-ACME', 'Acme Pty Ltd');
        $plan = Plan::factory()->for($ws)->active()->create(['start_date' => null, 'end_date' => null]);
        $plan->tiers()->create(['threshold_from' => 0, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Transaction::factory()->for($ws)->create(['external_id' => 'I1', 'amount' => 1000, 'raw_data' => ['contact_id' => 'C-ACME']]);

        $this->put(route('admin.customers.update', $acme), ['owner_user_id' => $alice->id, 'tags' => 'enterprise, nsw'])
            ->assertSessionHasNoErrors();

        $alias = Alias::sole();
        $this->assertSame(['contact_id', 'C-ACME', $alice->id], [$alias->match_field, $alias->alias_value, $alias->user_id]);
        $this->assertSame(['enterprise', 'nsw'], $acme->fresh()->tags->pluck('name')->sort()->values()->all());
        $this->assertSame('Alice', ActivityLog::where('action', 'customer.updated')->sole()->properties['rep']);
        $run = app(StartCalcRun::class)->handle($plan);
        $this->assertSame($alice->id, Credit::where('calc_run_id', $run->id)->sole()->user_id);

        // Reassigning moves the same alias; clearing removes it.
        $this->put(route('admin.customers.update', $acme), ['owner_user_id' => $bob->id]);
        $this->assertSame($bob->id, Alias::sole()->user_id);
        $this->put(route('admin.customers.update', $acme), ['owner_user_id' => '']);
        $this->assertSame(0, Alias::count());
        $this->assertNull($acme->fresh()->owner_user_id);
    }

    public function test_owner_must_be_a_member_of_this_workspace(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $outsider = $this->makeMember(Workspace::factory()->create(), Role::Participant);
        $acme = $this->customer($ws, 'C-ACME', 'Acme Pty Ltd');

        $this->put(route('admin.customers.update', $acme), ['owner_user_id' => $outsider->id])->assertSessionHasErrors('owner_user_id');
        $this->assertSame(0, Alias::count());
    }

    public function test_read_only_admins_see_customers_but_cannot_change_them(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);
        $acme = $this->customer($ws, 'C-ACME', 'Acme Pty Ltd');

        $this->get(route('admin.customers.index'))->assertOk()->assertSee('Acme Pty Ltd')->assertDontSee('name="owner_user_id"', false);
        $this->put(route('admin.customers.update', $acme), ['owner_user_id' => null])->assertForbidden();
    }

    public function test_participants_cannot_open_the_customer_list(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::Participant);

        $this->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_another_workspaces_customer_is_not_reachable(): void
    {
        $other = Workspace::factory()->create();
        $this->useWorkspace($other);
        $foreign = $this->customer($other, 'C-X', 'Foreign Co');
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.customers.index'))->assertDontSee('Foreign Co');
        $this->put(route('admin.customers.update', $foreign), ['owner_user_id' => null])->assertNotFound();
    }
}
