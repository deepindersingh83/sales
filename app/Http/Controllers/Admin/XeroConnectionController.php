<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Services\Connectors\Xero\XeroClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Connects a workspace to Xero via OAuth 2.0. Each authorised Xero
 * organisation becomes a daily recurring import source (type "xero") whose
 * tokens are stored encrypted; syncing then runs through the same
 * ScheduledImportRunner as CSV sources.
 */
class XeroConnectionController extends Controller
{
    private const STATE_KEY = 'xero_oauth_state';

    public function __construct(protected XeroClient $client) {}

    public function connect(Request $request): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        if (! $this->client->isConfigured()) {
            return redirect()->route('admin.connectors.index')
                ->withErrors(['xero' => 'Xero is not configured on this server (set XERO_CLIENT_ID and XERO_CLIENT_SECRET).']);
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($this->client->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        $expected = $request->session()->pull(self::STATE_KEY);

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->fail('Xero connection could not be verified — please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->fail('Xero connection was cancelled.');
        }

        try {
            $tokens = $this->client->exchangeCode((string) $request->query('code'));
            $tenants = $this->client->connections($tokens['access_token']);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Xero rejected the connection. Check the app credentials and redirect URI.');
        }

        if ($tenants === []) {
            return $this->fail('No Xero organisation was authorised.');
        }

        // Every organisation in this consent shares one rotating token grant.
        $tokens['grant_id'] = (string) Str::uuid();

        foreach ($tenants as $tenant) {
            $source = $this->sourceForTenant($tenant['tenantId']);

            $source->fill([
                'name' => 'Xero — '.$tenant['tenantName'],
                'config' => array_merge($source->config ?? [], [
                    'tenant_id' => $tenant['tenantId'],
                    'tenant_name' => $tenant['tenantName'],
                ]),
                'credentials' => array_merge($tokens, ['tenant_id' => $tenant['tenantId']]),
                'last_error' => null,
            ])->save();
        }

        $names = collect($tenants)->pluck('tenantName')->implode(', ');

        return redirect()->route('admin.import-sources.index')
            ->with('status', "Connected to Xero ({$names}). Invoices sync daily — use “Run now” for the first import.");
    }

    /**
     * The workspace's import source for a Xero organisation: the existing one
     * when reconnecting (so its sync cursor and history are kept), otherwise a
     * new daily source.
     */
    protected function sourceForTenant(string $tenantId): ImportSource
    {
        return ImportSource::where('type', 'xero')->get()
            ->first(fn (ImportSource $s) => ($s->config['tenant_id'] ?? null) === $tenantId)
            ?? new ImportSource(['type' => 'xero', 'schedule' => 'daily', 'next_run_at' => now()]);
    }

    protected function fail(string $message): RedirectResponse
    {
        return redirect()->route('admin.connectors.index')->withErrors(['xero' => $message]);
    }
}
