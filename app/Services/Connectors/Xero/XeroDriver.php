<?php

namespace App\Services\Connectors\Xero;

use App\Contracts\ImportSourceDriver;
use App\Models\ImportSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imports Xero sales invoices (ACCREC) as transactions.
 *
 * Mapping: external_id = InvoiceID, amount = SubTotal (net of tax — commission
 * is normally paid on net revenue), currency = CurrencyCode, date = invoice
 * date. PAID invoices are flagged is_paid (drives "pay when you get paid");
 * VOIDED invoices are flagged excluded. raw_data carries the customer, invoice
 * number, reference, item codes and every line-item tracking category (e.g. a
 * "Sales Rep" category becomes raw_data.sales_rep) so aliases can credit reps.
 *
 * Syncs are incremental: after the first full pull, only invoices modified
 * since the last successful sync are requested.
 */
class XeroDriver implements ImportSourceDriver
{
    /** Refresh the access token when it has less than this many seconds left. */
    private const TOKEN_LEEWAY_SECONDS = 120;

    public function __construct(protected XeroClient $client) {}

    public function key(): string
    {
        return 'xero';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(ImportSource $source, array $options = []): array
    {
        $accessToken = $this->freshAccessToken($source);
        $tenantId = $source->credentials['tenant_id'];

        $modifiedSince = isset($source->config['synced_through'])
            ? Carbon::parse($source->config['synced_through'])
            : null;

        // Voids only matter for invoices we may already hold, i.e. after the
        // first sync; the initial pull skips them.
        $statuses = $modifiedSince ? ['AUTHORISED', 'PAID', 'VOIDED'] : ['AUTHORISED', 'PAID'];

        $rows = [];
        $page = 1;
        do {
            $invoices = $this->client->invoices($accessToken, $tenantId, $page, $modifiedSince, $statuses);
            foreach ($invoices as $invoice) {
                $rows[] = $this->mapInvoice($invoice);
            }
            $page++;
        } while (count($invoices) >= XeroClient::PAGE_SIZE);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    public function mapInvoice(array $invoice): array
    {
        $status = (string) ($invoice['Status'] ?? '');
        $lines = $invoice['LineItems'] ?? [];
        $itemCodes = collect($lines)->pluck('ItemCode')->filter()->unique()->values();

        $raw = [
            'invoice_number' => $invoice['InvoiceNumber'] ?? null,
            'reference' => $invoice['Reference'] ?? null,
            'customer' => data_get($invoice, 'Contact.Name'),
            'contact_id' => data_get($invoice, 'Contact.ContactID'),
            'status' => $status,
            'product' => $itemCodes->first() ?? data_get($lines, '0.Description'),
            'item_codes' => $itemCodes->implode(','),
            'total' => $invoice['Total'] ?? null,
            'total_tax' => $invoice['TotalTax'] ?? null,
            'amount_due' => $invoice['AmountDue'] ?? null,
        ];

        // Tracking categories (e.g. "Sales Rep", "Region") from the line items;
        // the first option seen for each category wins.
        foreach ($lines as $line) {
            foreach ($line['Tracking'] ?? [] as $tracking) {
                $key = Str::snake(Str::lower(trim((string) ($tracking['Name'] ?? ''))));
                if ($key !== '' && ! isset($raw[$key])) {
                    $raw[$key] = $tracking['Option'] ?? null;
                }
            }
        }

        return [
            'external_id' => $invoice['InvoiceID'] ?? '',
            'amount' => $invoice['SubTotal'] ?? 0,
            'currency' => $invoice['CurrencyCode'] ?? null,
            'transaction_date' => $this->invoiceDate($invoice),
            'is_paid' => $status === 'PAID',
            'excluded' => $status === 'VOIDED',
            'raw_data' => $raw,
        ];
    }

    /**
     * Xero JSON dates come either as DateString ("2026-01-15T00:00:00") or in
     * the legacy "/Date(1768435200000+0000)/" form.
     *
     * @param  array<string, mixed>  $invoice
     */
    protected function invoiceDate(array $invoice): ?string
    {
        if (! empty($invoice['DateString'])) {
            return substr((string) $invoice['DateString'], 0, 10);
        }

        if (preg_match('#/Date\((-?\d+)#', (string) ($invoice['Date'] ?? ''), $m)) {
            return Carbon::createFromTimestampMs((int) $m[1], 'UTC')->toDateString();
        }

        return null;
    }

    /** Return a valid access token, refreshing (and persisting) it when near expiry. */
    protected function freshAccessToken(ImportSource $source): string
    {
        $credentials = $source->credentials ?? [];

        if (empty($credentials['refresh_token']) || empty($credentials['tenant_id'])) {
            throw new \RuntimeException('Xero connection is incomplete — reconnect Xero.');
        }

        $expiresAt = isset($credentials['expires_at']) ? Carbon::parse($credentials['expires_at']) : null;

        if ($expiresAt && $expiresAt->gt(now()->addSeconds(self::TOKEN_LEEWAY_SECONDS))) {
            return $credentials['access_token'];
        }

        $tokens = $this->client->refresh($credentials['refresh_token']);
        $source->forceFill(['credentials' => array_merge($credentials, $tokens)])->save();

        return $tokens['access_token'];
    }
}
