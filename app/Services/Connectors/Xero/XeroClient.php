<?php

namespace App\Services\Connectors\Xero;

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

    /** Has the platform operator supplied Xero app credentials? */
    public function isConfigured(): bool
    {
        return filled(config('services.xero.client_id')) && filled(config('services.xero.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.xero.redirect') ?: route('admin.connectors.xero.callback');
    }

    /** URL that sends the admin to Xero's consent screen. */
    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('services.xero.scopes'),
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
     * @return array<int, array{tenantId:string, tenantName:string, tenantType:string}>
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
        $json = Http::asForm()
            ->withBasicAuth(config('services.xero.client_id'), config('services.xero.client_secret'))
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
