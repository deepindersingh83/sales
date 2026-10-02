<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\Contest;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitedAdminReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_limited_admin_cannot_change_products(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);
        $product = Product::create(['workspace_id' => $ws->id, 'sku' => 'P-1', 'name' => 'Pro']);

        $this->get(route('admin.products.index'))->assertOk()->assertDontSee('Add product');
        $this->post(route('admin.products.store'), ['sku' => 'P-2', 'name' => 'New'])->assertForbidden();
        $this->put(route('admin.products.update', $product), ['sku' => 'P-1', 'name' => 'Renamed'])->assertForbidden();
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertSame(['Pro'], Product::pluck('name')->all());
    }

    public function test_limited_admin_cannot_change_contests(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);
        $contest = Contest::create(['workspace_id' => $ws->id, 'name' => 'Q4 blitz', 'metric' => 'credited']);

        $this->post(route('admin.contests.store'), ['name' => 'Rogue', 'metric' => 'credited'])->assertForbidden();
        $this->delete(route('admin.contests.destroy', $contest))->assertForbidden();

        $this->assertSame(['Q4 blitz'], Contest::pluck('name')->all());
    }

    public function test_plan_admin_can_still_manage_products(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::PlanAdmin);

        $this->post(route('admin.products.store'), ['sku' => 'P-2', 'name' => 'New'])->assertRedirect();

        $this->assertSame(1, Product::count());
    }
}
