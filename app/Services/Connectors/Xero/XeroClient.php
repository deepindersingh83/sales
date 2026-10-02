<?php

namespace App\Services\Connectors\Xero;

use App\Models\IntegrationSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over Xero's OAuth 2.0 (authorization-code) flow and the
 * Accounting API endpoints the connector needs. Stateless: callers pass and
 * persist the tokens. All calls go through Laravel's HTTP client so tests can
 * fake Xero entirely.
 */
class XeroClient
{
    public const AUTHORIZE_URL = 'https://login.xero.com/identity/connect/authorize';

    public const TOKEN_URL = 'https://identity.xero.com/connect/token';

    public const CONNECTIONS_URL = 'https://api.xero.com/connections';

    public const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    /** Xero returns at most this many invoices per page. */
    public const PAGE_SIZE = 100;

    /** Have Xero app credentials been supplied (on the Integrations page or in .env)? */
    public function isConfigured(): bool
    {
        $app = $this->app();

        return filled($app['client_id']) && filled($app['client_secret']);
    }

    /**
     * The Xero app credentials in use: the workspace's own keys saved on the
     * Integrations page win over the server-wide .env values.
     *
     * @return array{client_id:?string, client_secret:?string, scopes:string, source:string}
     */
    public function app(): array
    {
        $saved = IntegrationSetting::for('xero');
        $useSaved = filled($saved['client_id'] ?? null) && filled($saved['client_secret'] ?? null);

        return [
            'client_id' => $useSaved ? $saved['client_id'] : config('services.xero.client_id'),
            'client_secret' => $useSaved ? $saved['client_secret'] : config('services.xero.client_secret'),
            'scopes' => ($useSaved ? ($saved['scopes'] ?? null) : null) ?: config('services.xero.scopes'),
            'source' => $useSaved ? 'workspace' : 'server',
        ];
    }

    public function redirectUri(): string
    {
        return config('services.xero.redirect') ?: route('admin.connectors.xero.callback');
    }

    /** Revoke one organisation connection at Xero (the app loses access to it). */
    public function revoke(string $accessToken, string $connectionId): void
    {
        Http::withToken($accessToken)->delete(self::CONNECTIONS_URL.'/'.$connectionId)->throw();
    }

    /** URL that sends the admin to Xero's consent screen. */
    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->app()['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->app()['scopes'],
            'state' => $state,
        ]);
    }

    /**
     * Exchange the consent-screen code for tokens.
     *
     * @return array{access_token:string, refresh_token:string, expires_at:string}
     */
    public function exchangeCode(string $code): array
    {
        return $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    /**
     * Obtain a fresh access token. Xero rotates the refresh token on every use,
     * so the caller MUST persist the returned refresh_token.
     *
     * @return array{access_token:string, refresh_token:string, expires_at:string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Organisations (tenants) the token was granted access to.
     *
     * @return array<int, array{id:string, tenantId:string, tenantName:string, tenantType:string}>
     */
    public function connections(string $accessToken): array
    {
        return Http::withToken($accessToken)->acceptJson()
            ->get(self::CONNECTIONS_URL)
            ->throw()
            ->json() ?? [];
    }

    /**
     * One page of sales invoices (ACCREC) for a tenant, optionally only those
     * modified since a timestamp (incremental sync).
     *
     * @param  array<int, string>  $statuses
     * @return array<int, array<string, mixed>>
     */
    public function invoices(string $accessToken, string $tenantId, int $page, ?Carbon $modifiedSince, array $statuses): array
    {
        $request = $this->api($accessToken, $tenantId);

        if ($modifiedSince) {
            $request->withHeaders(['If-Modified-Since' => $modifiedSince->copy()->utc()->format('Y-m-d\TH:i:s')]);
        }

        $response = $request->get(self::INVOICES_URL, [
            'page' => $page,
            'where' => 'Type=="ACCREC"',
            'Statuses' => implode(',', $statuses),
        ]);

        // Xero answers 304 when nothing changed since If-Modified-Since.
        if ($response->status() === 304) {
            return [];
        }

        return $response->throw()->json('Invoices') ?? [];
    }

    protected function api(string $accessToken, string $tenantId): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders(['xero-tenant-id' => $tenantId])
            ->retry(3, 1000, fn (\Throwable $e) => $e instanceof RequestException && $e->response->status() === 429, throw: false);
    }

    /**
     * @param  array<string, string>  $form
     * @return array{access_token:string, refresh_token:string, expires_at:string}
     */
    protected function requestTokens(array $form): array
    {
        $app = $this->app();

        $json = Http::asForm()
            ->withBasicAuth((string) $app['client_id'], (string) $app['client_secret'])
            ->post(self::TOKEN_URL, $form)
            ->throw()
            ->json();

        return [
            'access_token' => $json['access_token'],
            'refresh_token' => $json['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($json['expires_in'] ?? 1800))->toIso8601String(),
        ];
    }
}
