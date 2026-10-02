<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Connectors\Xero\XeroClient;
use App\Services\Import\ScheduledImportRunner;
use App\Services\Import\TransactionUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.xero.client_id' => 'xero-client',
            'services.xero.client_secret' => 'xero-secret',
        ]);
        Http::preventStrayRequests();
    }

    private function invoice(string $id, string $status, float $subTotal, array $extra = []): array
    {
        return array_merge([
            'InvoiceID' => $id,
            'InvoiceNumber' => 'INV-'.$id,
            'Type' => 'ACCREC',
            'Status' => $status,
            'Contact' => ['ContactID' => 'C-1', 'Name' => 'Acme Pty Ltd'],
            'DateString' => '2026-08-15T00:00:00',
            'SubTotal' => $subTotal,
            'TotalTax' => $subTotal * 0.1,
            'Total' => $subTotal * 1.1,
            'CurrencyCode' => 'AUD',
            'LineItems' => [[
                'ItemCode' => 'PRO',
                'Description' => 'Pro plan',
                'Tracking' => [['Name' => 'Sales Rep', 'Option' => 'Alice']],
            ]],
        ], $extra);
    }

    private function connectedSource(Workspace $ws, array $credentials = []): ImportSource
    {
        return ImportSource::create([
            'workspace_id' => $ws->id,
            'name' => 'Xero — Demo Co',
            'type' => 'xero',
            'schedule' => 'daily',
            'config' => ['tenant_id' => 'T-1', 'tenant_name' => 'Demo Co'],
            'credentials' => array_merge([
                'access_token' => 'access-1',
                'refresh_token' => 'refresh-1',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'tenant_id' => 'T-1',
            ], $credentials),
        ]);
    }

    public function test_connect_redirects_to_xero_consent_with_state(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $response = $this->get(route('admin.connectors.xero.connect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(XeroClient::AUTHORIZE_URL, $location);
        $this->assertStringContainsString('client_id=xero-client', $location);
        $this->assertStringContainsString('state='.session('xero_oauth_state'), $location);
    }

    public function test_connect_explains_when_the_server_has_no_xero_app(): void
    {
        config(['services.xero.client_id' => null]);
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.connectors.xero.connect'))
            ->assertRedirect(route('admin.connectors.xero.show'))
            ->assertSessionHasErrors('xero');
    }

    public function test_read_only_admin_cannot_connect_xero(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::LimitedAdmin);

        $this->get(route('admin.connectors.xero.connect'))->assertForbidden();
    }

    public function test_integrations_page_offers_xero_only_and_lists_the_connection(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.connectors.index'))
            ->assertOk()
            ->assertSee(route('admin.connectors.xero.show'))
            ->assertSee('Coming soon');

        $source = $this->connectedSource($ws);
        $source->update(['last_error' => 'Token revoked']);

        $this->get(route('admin.import-sources.index'))
            ->assertOk()
            ->assertSee('Xero — Demo Co')
            ->assertSee(route('admin.import-sources.run', $source))
            ->assertSee('Last sync failed: Token revoked')
            ->assertDontSee('refresh-1');
    }

    public function test_callback_rejects_a_mismatched_state(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->withSession(['xero_oauth_state' => 'expected'])
            ->get(route('admin.connectors.xero.callback', ['state' => 'forged', 'code' => 'abc']))
            ->assertRedirect(route('admin.connectors.xero.show'))
            ->assertSessionHasErrors('xero');

        $this->assertSame(0, ImportSource::count());
    }

    public function test_callback_handles_a_cancelled_consent(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->withSession(['xero_oauth_state' => 'good'])
            ->get(route('admin.connectors.xero.callback', ['state' => 'good', 'error' => 'access_denied']))
            ->assertRedirect(route('admin.connectors.xero.show'))
            ->assertSessionHasErrors(['xero' => 'Xero connection was cancelled.']);

        $this->assertSame(0, ImportSource::count());
    }

    public function test_callback_reports_a_rejected_token_exchange(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        Http::fake([XeroClient::TOKEN_URL => Http::response(['error' => 'invalid_client'], 400)]);

        $this->withSession(['xero_oauth_state' => 'good'])
            ->get(route('admin.connectors.xero.callback', ['state' => 'good', 'code' => 'abc']))
            ->assertRedirect(route('admin.connectors.xero.show'))
            ->assertSessionHasErrors('xero');

        $this->assertSame(0, ImportSource::count());
    }

    public function test_callback_requires_at_least_one_organisation(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        Http::fake([
            XeroClient::TOKEN_URL => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 1800]),
            XeroClient::CONNECTIONS_URL => Http::response([]),
        ]);

        $this->withSession(['xero_oauth_state' => 'good'])
            ->get(route('admin.connectors.xero.callback', ['state' => 'good', 'code' => 'abc']))
            ->assertSessionHasErrors(['xero' => 'No Xero organisation was authorised.']);

        $this->assertSame(0, ImportSource::count());
    }

    public function test_callback_creates_an_encrypted_daily_source_per_organisation(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);

        Http::fake([
            XeroClient::TOKEN_URL => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 1800]),
            XeroClient::CONNECTIONS_URL => Http::response([['tenantId' => 'T-1', 'tenantName' => 'Demo Co', 'tenantType' => 'ORGANISATION']]),
        ]);

        $this->withSession(['xero_oauth_state' => 'good'])
            ->get(route('admin.connectors.xero.callback', ['state' => 'good', 'code' => 'abc']))
            ->assertRedirect(route('admin.connectors.xero.show'));

        $source = ImportSource::sole();
        $this->assertSame('xero', $source->type);
        $this->assertSame('daily', $source->schedule);
        $this->assertSame('Xero — Demo Co', $source->name);
        $this->assertSame('refresh-1', $source->credentials['refresh_token']);
        $this->assertStringNotContainsString('refresh-1', DB::table('import_sources')->value('credentials'));
    }

    public function test_sync_imports_sales_invoices_as_transactions(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [
                $this->invoice('A', 'AUTHORISED', 1000),
                $this->invoice('B', 'PAID', 500),
            ]]),
        ]);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(2, $result['created']);
        $this->useWorkspace($ws);
        $a = Transaction::where('external_id', 'A')->sole();
        $this->assertSame('xero', $a->source_system);
        $this->assertEqualsWithDelta(1000.0, (float) $a->amount, 0.001); // SubTotal, net of tax
        $this->assertSame('AUD', $a->currency);
        $this->assertSame('2026-08-15', $a->transaction_date->toDateString());
        $this->assertFalse($a->is_paid);
        $this->assertSame('Acme Pty Ltd', $a->raw_data['customer']);
        $this->assertSame('PRO', $a->raw_data['product']);
        $this->assertSame('Alice', $a->raw_data['sales_rep']);
        $this->assertTrue(Transaction::where('external_id', 'B')->sole()->is_paid);

        Http::assertSent(fn (Request $r) => $r->hasHeader('xero-tenant-id', 'T-1')
            && ! $r->hasHeader('If-Modified-Since')
            && str_contains(urldecode($r->url()), 'Statuses=AUTHORISED,PAID'));
        $this->assertNotNull($source->fresh()->config['synced_through']);
    }

    public function test_incremental_sync_updates_changed_invoices_and_excludes_voids(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);
        $source->update(['config' => array_merge($source->config, ['synced_through' => '2026-09-01T00:00:00+00:00'])]);
        $this->useWorkspace($ws);
        Transaction::create(['workspace_id' => $ws->id, 'external_id' => 'A', 'source_system' => 'xero', 'amount' => 1000, 'currency' => 'AUD', 'raw_data' => []]);

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [$this->invoice('A', 'VOIDED', 1000)]]),
        ]);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], $result);
        $this->assertTrue(Transaction::where('external_id', 'A')->sole()->excluded);
        Http::assertSent(fn (Request $r) => $r->hasHeader('If-Modified-Since', '2026-09-01T00:00:00'));
    }

    public function test_sync_follows_pagination(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);
        $fullPage = array_map(fn ($i) => $this->invoice("P1-{$i}", 'AUTHORISED', 10), range(1, XeroClient::PAGE_SIZE));

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices*' => Http::sequence()
                ->push(['Invoices' => $fullPage])
                ->push(['Invoices' => [$this->invoice('P2-1', 'AUTHORISED', 10)]]),
        ]);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(XeroClient::PAGE_SIZE + 1, $result['created']);
    }

    public function test_expired_token_is_refreshed_and_rotated_token_persisted(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws, ['expires_at' => now()->subMinute()->toIso8601String()]);

        Http::fake([
            XeroClient::TOKEN_URL => Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 1800]),
            'api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => []]),
        ]);

        app(ScheduledImportRunner::class)->run($source);

        $this->assertSame('refresh-2', $source->fresh()->credentials['refresh_token']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'Invoices') && $r->hasHeader('Authorization', 'Bearer access-2'));
    }

    public function test_refresh_rotates_the_token_for_every_organisation_on_the_grant(): void
    {
        $ws = Workspace::factory()->create();
        $expired = ['expires_at' => now()->subMinute()->toIso8601String(), 'grant_id' => 'G-1'];
        $first = $this->connectedSource($ws, $expired);
        $second = $this->connectedSource($ws, $expired + ['tenant_id' => 'T-2']);
        $otherGrant = $this->connectedSource($ws, ['grant_id' => 'G-2', 'refresh_token' => 'other-refresh']);

        Http::fake([
            XeroClient::TOKEN_URL => Http::sequence()
                ->push(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 1800])
                ->whenEmpty(Http::response(['error' => 'invalid_grant'], 400)),
            'api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => []]),
        ]);

        app(ScheduledImportRunner::class)->run($first);
        app(ScheduledImportRunner::class)->run($second->fresh());

        // The second organisation adopted the rotated token instead of reusing the spent one.
        Http::assertSentCount(3);
        $this->assertSame('refresh-2', $second->fresh()->credentials['refresh_token']);
        $this->assertSame('T-2', $second->fresh()->credentials['tenant_id']);
        $this->assertSame('other-refresh', $otherGrant->fresh()->credentials['refresh_token']);
    }

    public function test_a_failure_while_saving_transactions_is_recorded_on_the_source(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);
        Http::fake(['api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [$this->invoice('A', 'PAID', 10)]])]);
        $this->mock(TransactionUpserter::class)->shouldReceive('upsert')->andThrow(new \RuntimeException('Deadlock found'));

        try {
            app(ScheduledImportRunner::class)->run($source);
            $this->fail('Expected the sync to throw.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('Deadlock found', $source->fresh()->last_error);
    }

    public function test_a_feed_whose_rows_are_all_skipped_is_flagged(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);
        Http::fake(['api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [$this->invoice('', 'PAID', 10)]])]);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('All 1 rows were skipped', $source->fresh()->last_error);
    }

    public function test_failed_sync_records_the_error_and_keeps_the_cursor(): void
    {
        $ws = Workspace::factory()->create();
        $source = $this->connectedSource($ws);

        Http::fake(['api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Title' => 'Unauthorized'], 401)]);

        try {
            app(ScheduledImportRunner::class)->run($source);
            $this->fail('Expected the sync to throw.');
        } catch (RequestException) {
        }

        $fresh = $source->fresh();
        $this->assertNotNull($fresh->last_error);
        $this->assertArrayNotHasKey('synced_through', $fresh->config);
        $this->assertNull($fresh->last_synced_at);
    }
}
