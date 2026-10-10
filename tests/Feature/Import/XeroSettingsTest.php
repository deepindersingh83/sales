<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Models\ImportSource;
use App\Models\IntegrationSetting;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Connectors\Xero\XeroClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.xero.client_id' => null, 'services.xero.client_secret' => null]);
        Http::preventStrayRequests();
    }

    private function connectedSource(Workspace $ws, array $config = []): ImportSource
    {
        return ImportSource::create([
            'workspace_id' => $ws->id, 'name' => 'Xero — Demo Co', 'type' => 'xero', 'schedule' => 'daily',
            'config' => array_merge(['tenant_id' => 'T-1', 'tenant_name' => 'Demo Co', 'connection_id' => 'C-1'], $config),
            'credentials' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1',
                'expires_at' => now()->addMinutes(30)->toIso8601String(), 'tenant_id' => 'T-1'],
        ]);
    }

    public function test_full_admin_saves_app_keys_in_the_browser_and_they_are_used(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.connectors.xero.show'))->assertOk()->assertSee('Not set up');

        $this->put(route('admin.connectors.xero.app.update'), ['client_id' => 'ws-client', 'client_secret' => 'ws-secret'])
            ->assertRedirect(route('admin.connectors.xero.show'));

        $this->assertStringNotContainsString('ws-secret', DB::table('integration_settings')->value('settings'));
        $this->assertTrue(app(XeroClient::class)->isConfigured());
        $this->assertStringContainsString('client_id=ws-client', app(XeroClient::class)->authorizeUrl('s'));
        $this->get(route('admin.connectors.xero.show'))
            ->assertSee('Using keys saved here')
            ->assertDontSee('ws-secret');
    }

    public function test_a_blank_secret_keeps_the_saved_one(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        IntegrationSetting::create(['workspace_id' => $ws->id, 'provider' => 'xero',
            'settings' => ['client_id' => 'old', 'client_secret' => 'kept-secret']]);

        $this->put(route('admin.connectors.xero.app.update'), ['client_id' => 'new-id', 'client_secret' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(['client_id' => 'new-id', 'client_secret' => 'kept-secret', 'scopes' => null], IntegrationSetting::for('xero'));
    }

    public function test_saved_keys_are_used_for_token_refresh(): void
    {
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);
        IntegrationSetting::create(['workspace_id' => $ws->id, 'provider' => 'xero',
            'settings' => ['client_id' => 'ws-client', 'client_secret' => 'ws-secret']]);
        Http::fake([XeroClient::TOKEN_URL => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 1800])]);

        app(XeroClient::class)->refresh('old-refresh');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('ws-client:ws-secret')));
    }

    public function test_only_full_admins_change_app_keys(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::PlanAdmin);

        $this->put(route('admin.connectors.xero.app.update'), ['client_id' => 'x', 'client_secret' => 'y'])->assertForbidden();
        $this->delete(route('admin.connectors.xero.app.destroy'))->assertForbidden();
        $this->assertSame(0, IntegrationSetting::count());
    }

    public function test_check_connection_reports_healthy_and_broken_connections(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $source = $this->connectedSource($ws);

        Http::fake([XeroClient::CONNECTIONS_URL => Http::sequence()
            ->push([['id' => 'C-1', 'tenantId' => 'T-1', 'tenantName' => 'Demo Co', 'tenantType' => 'ORGANISATION']])
            ->push([])                                // organisation de-authorised in Xero
            ->push(['Title' => 'Unauthorized'], 401), // token revoked
        ]);

        $this->post(route('admin.connectors.xero.check', $source))->assertSessionHas('status');
        $this->assertTrue($source->fresh()->config['last_check_ok']);

        $this->post(route('admin.connectors.xero.check', $source))->assertSessionHas('check_error');
        $this->assertFalse($source->fresh()->config['last_check_ok']);
        $this->get(route('admin.connectors.xero.show'))->assertSee('Needs attention')->assertSee('no longer authorised');

        $this->post(route('admin.connectors.xero.check', $source))->assertSessionHas('check_error');
        $this->assertStringContainsString('HTTP 401', $source->fresh()->config['last_check_message']);
        $this->assertSame('C-1', $source->fresh()->config['connection_id']); // other config untouched
    }

    public function test_schedule_can_be_changed_or_set_to_manual(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $source = $this->connectedSource($ws);

        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'hourly']);
        $this->assertSame('hourly', $source->fresh()->schedule);
        $this->assertTrue($source->fresh()->next_run_at->lte(now()->addHour()));

        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'manual']);
        $this->assertNull($source->fresh()->schedule);
        $this->assertNull($source->fresh()->next_run_at);
        $this->assertFalse($source->fresh()->isDue());

        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'yearly'])->assertSessionHasErrors('schedule');
    }

    public function test_disconnect_revokes_at_xero_and_keeps_imported_transactions(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $source = $this->connectedSource($ws);
        $transaction = Transaction::factory()->for($ws)->create(['import_source_id' => $source->id, 'source_system' => 'xero']);
        Http::fake([XeroClient::CONNECTIONS_URL.'/C-1' => Http::response(null, 204)]);

        $this->delete(route('admin.connectors.xero.destroy', $source))->assertRedirect(route('admin.connectors.xero.show'));

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === XeroClient::CONNECTIONS_URL.'/C-1');
        $this->assertSame(0, ImportSource::count());
        $this->assertNotNull($transaction->fresh());
    }

    public function test_disconnect_still_works_when_xero_is_unreachable(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $source = $this->connectedSource($ws);
        Http::fake([XeroClient::CONNECTIONS_URL.'/C-1' => Http::response(null, 500)]);

        $this->delete(route('admin.connectors.xero.destroy', $source))->assertRedirect();

        $this->assertSame(0, ImportSource::count());
    }

    public function test_read_only_admins_see_the_page_but_cannot_act(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);
        $source = $this->connectedSource($ws);

        $this->get(route('admin.connectors.xero.show'))->assertOk()->assertSee('Demo Co')->assertDontSee('Disconnect');
        $this->post(route('admin.connectors.xero.check', $source))->assertForbidden();
        $this->put(route('admin.connectors.xero.update', $source), ['schedule' => 'hourly'])->assertForbidden();
        $this->delete(route('admin.connectors.xero.destroy', $source))->assertForbidden();
    }

    public function test_csv_sources_cannot_be_managed_through_the_xero_routes(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $csv = ImportSource::create(['workspace_id' => $ws->id, 'name' => 'Feed', 'type' => 'csv', 'schedule' => 'daily']);

        $this->delete(route('admin.connectors.xero.destroy', $csv))->assertNotFound();
    }
}
