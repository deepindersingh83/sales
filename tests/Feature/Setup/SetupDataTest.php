<?php

namespace Tests\Feature\Setup;

use App\Enums\Role;
use App\Models\ImportSource;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Import\ScheduledImportRunner;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SetupDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_tagged_product(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->post(route('admin.products.store'), [
            'sku' => 'SW-PRO',
            'name' => 'Pro Subscription',
            'category' => 'Software',
            'list_price' => 1200,
            'currency' => 'usd',
            'active' => '1',
            'tags' => 'saas, recurring',
        ])->assertRedirect();

        $product = Product::where('sku', 'SW-PRO')->firstOrFail();
        $this->assertSame('USD', $product->currency);
        $this->assertEqualsCanonicalizing(['saas', 'recurring'], $product->tags->pluck('name')->all());
        $this->assertSame(1, Tag::where('name', 'saas')->count());
    }

    public function test_product_sku_is_unique_per_workspace(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        Product::factory()->for($ws)->create(['sku' => 'DUP']);

        $this->from(route('admin.products.index'))
            ->post(route('admin.products.store'), ['sku' => 'DUP', 'name' => 'Another'])
            ->assertSessionHasErrors('sku');
    }

    public function test_global_search_finds_products_and_transactions(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        Product::factory()->for($ws)->create(['name' => 'Widget Deluxe', 'sku' => 'WID-1']);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'INV-777', 'source_system' => 'csv', 'amount' => 10, 'currency' => 'USD', 'raw_data' => []]);

        $this->get(route('search.index', ['q' => 'Widget']))->assertOk()->assertSee('Widget Deluxe');
        $this->get(route('search.index', ['q' => 'INV-777']))->assertOk()->assertSee('INV-777');
    }

    public function test_bulk_member_import_creates_users_and_manager_lines(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $csv = "name,email,role,manager_email,salary\n"
            ."Mia Chen,mia@acme.test,plan_admin,,120000\n"
            ."Rick Ford,rick@acme.test,participant,mia@acme.test,80000\n";
        $file = UploadedFile::fake()->createWithContent('team.csv', $csv);

        $this->post(route('admin.members.import.store'), ['file' => $file])->assertRedirect(route('admin.members.index'));

        $mia = $ws->users()->where('email', 'mia@acme.test')->first();
        $rick = $ws->users()->where('email', 'rick@acme.test')->first();
        $this->assertNotNull($mia);
        $this->assertSame(Role::PlanAdmin->value, $mia->pivot->role);
        $this->assertSame((int) $mia->id, (int) $rick->pivot->manager_id);
    }

    public function test_scheduled_import_runner_imports_from_a_file(): void
    {
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);

        $path = sys_get_temp_dir().'/demo_import_'.uniqid().'.csv';
        file_put_contents($path, "external_id,amount,currency\nSCH-1,100,USD\nSCH-2,250,USD\n");

        $source = ImportSource::create([
            'workspace_id' => $ws->id,
            'name' => 'Nightly feed',
            'type' => 'csv',
            'schedule' => 'daily',
            'source_path' => $path,
            'config' => ['mapping' => ['external_id' => 'external_id', 'amount' => 'amount', 'currency' => 'currency']],
        ]);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, Transaction::where('source_system', 'csv')->count());
        $this->assertNotNull($source->fresh()->last_synced_at);
        $this->assertNotNull($source->fresh()->next_run_at);

        @unlink($path);
    }

    public function test_admin_can_save_white_label_branding(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->put(route('admin.settings.update'), [
            'name' => $ws->name,
            'base_currency' => 'USD',
            'brand_name' => 'Acme Commissions',
            'brand_color' => '#ff0055',
            'logo_url' => 'https://example.com/logo.png',
        ])->assertRedirect();

        $ws->refresh();
        $this->assertSame('Acme Commissions', $ws->brand_name);
        $this->assertSame('Acme Commissions', $ws->displayName());
        $this->assertSame('#ff0055', $ws->brand_color);
    }

    public function test_invalid_brand_color_is_rejected(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), ['name' => $ws->name, 'base_currency' => 'USD', 'brand_color' => 'notacolor'])
            ->assertSessionHasErrors('brand_color');
    }

    public function test_demo_seeder_builds_a_workspace(): void
    {
        $this->seed(DemoSeeder::class);

        $ws = Workspace::where('slug', 'acme-demo')->firstOrFail();
        $this->useWorkspace($ws);

        $this->assertSame(5, $ws->users()->count());
        $this->assertGreaterThanOrEqual(5, Product::where('workspace_id', $ws->id)->count());
        $this->assertGreaterThan(0, Transaction::where('workspace_id', $ws->id)->count());
    }
}
