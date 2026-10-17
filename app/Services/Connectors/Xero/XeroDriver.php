<?php

namespace App\Services\Connectors\Xero;

use App\Contracts\ImportSourceDriver;
use App\Models\Customer;
use App\Models\ImportSource;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Imports Xero sales invoices (ACCREC) and sales credit notes (ACCRECCREDIT)
 * as transactions, and syncs customer contacts into the customer list.
 *
 * Mapping: external_id = InvoiceID, amount = SubTotal (net of tax — commission
 * is normally paid on net revenue), currency = CurrencyCode, date = invoice
 * date. PAID invoices are flagged is_paid (drives "pay when you get paid");
 * VOIDED invoices are flagged excluded. raw_data carries the customer, invoice
 * number, reference, item codes and every line-item tracking category (e.g. a
 * "Sales Rep" category becomes raw_data.sales_rep) so aliases can credit reps.
 *
 * Credit notes become NEGATIVE transactions (amount = -SubTotal) so refunds
 * reduce revenue and commission; their remaining (unallocated) credit is kept
 * as amount_due so customer balances net it off. Invoices also record their
 * due date (aged balances) and FullyPaidOnDate as paid_at (pay-when-paid
 * plans count a deal in the period its cash arrived).
 *
 * Syncs are incremental: after the first full pull, only records modified
 * since the last successful sync are requested.
 */
class XeroDriver implements ImportSourceDriver
{
    /** Refresh the access token when it has less than this many seconds left. */
    private const TOKEN_LEEWAY_SECONDS = 120;

    /**
     * Bumped when the import starts bringing in new data (v2: credit notes,
     * due and paid dates). A source last synced by an older version gets one
     * full sync so history is back-filled, then goes incremental again.
     */
    public const SYNC_VERSION = 2;

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

        $modifiedSince = isset($source->config['synced_through']) && ($source->config['sync_version'] ?? 1) >= self::SYNC_VERSION
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

        $page = 1;
        do {
            $creditNotes = $this->client->creditNotes($accessToken, $tenantId, $page, $modifiedSince);
            foreach ($creditNotes as $creditNote) {
                if (in_array($creditNote['Status'] ?? '', $statuses, true)) {
                    $rows[] = $this->mapCreditNote($creditNote);
                }
            }
            $page++;
        } while (count($creditNotes) >= XeroClient::PAGE_SIZE);

        return $rows;
    }

    /**
     * Upsert this organisation's customer contacts into the customer list.
     * Incremental after the first run, like transactions.
     *
     * @return int The number of contacts created or updated.
     */
    public function syncContacts(ImportSource $source): int
    {
        $accessToken = $this->freshAccessToken($source);
        $tenantId = $source->credentials['tenant_id'];
        $modifiedSince = isset($source->config['contacts_synced_through'])
            ? Carbon::parse($source->config['contacts_synced_through'])
            : null;

        $count = 0;
        $page = 1;
        do {
            $contacts = $this->client->contacts($accessToken, $tenantId, $page, $modifiedSince);
            foreach ($contacts as $contact) {
                if (empty($contact['ContactID']) || blank($contact['Name'] ?? null)) {
                    continue;
                }

                Customer::updateOrCreate(
                    ['source_system' => 'xero', 'external_id' => $contact['ContactID']],
                    [
                        'import_source_id' => $source->id,
                        'name' => $contact['Name'],
                        'email' => $contact['EmailAddress'] ?? null,
                        'phone' => $this->primaryPhone($contact),
                        'status' => ($contact['ContactStatus'] ?? 'ACTIVE') === 'ARCHIVED' ? 'archived' : 'active',
                        'raw_data' => array_filter([
                            'account_number' => $contact['AccountNumber'] ?? null,
                            'tax_number' => $contact['TaxNumber'] ?? null,
                            'first_name' => $contact['FirstName'] ?? null,
                            'last_name' => $contact['LastName'] ?? null,
                        ], fn ($v) => filled($v)),
                    ],
                );
                $count++;
            }
            $page++;
        } while (count($contacts) >= XeroClient::PAGE_SIZE);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    protected function primaryPhone(array $contact): ?string
    {
        $phone = collect($contact['Phones'] ?? [])
            ->first(fn ($p) => filled($p['PhoneNumber'] ?? null));

        return $phone
            ? trim(implode(' ', array_filter([$phone['PhoneCountryCode'] ?? null, $phone['PhoneAreaCode'] ?? null, $phone['PhoneNumber']])))
            : null;
    }

    /**
     * Check the connection end to end: a usable token (refreshing it if
     * needed) and this organisation still authorised for the app.
     *
     * @return array{ok:bool, message:string}
     */
    public function check(ImportSource $source): array
    {
        try {
            $token = $this->freshAccessToken($source);
            $tenant = collect($this->client->connections($token))
                ->firstWhere('tenantId', $source->credentials['tenant_id'] ?? null);
        } catch (RequestException $e) {
            return ['ok' => false, 'message' => "Xero rejected the connection (HTTP {$e->response->status()}). Reconnect Xero."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $tenant
            ? ['ok' => true, 'message' => "Connected to {$tenant['tenantName']}."]
            : ['ok' => false, 'message' => 'This organisation is no longer authorised for the app. Reconnect Xero.'];
    }

    /**
     * Revoke this organisation's access at Xero. Best effort: a connection that
     * is already dead must still be removable locally, so failures are
     * reported, not thrown.
     */
    public function revoke(ImportSource $source): void
    {
        $connectionId = $source->config['connection_id'] ?? null;
        if ($connectionId === null) {
            return;
        }

        try {
            $this->client->revoke($this->freshAccessToken($source), $connectionId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    public function mapInvoice(array $invoice): array
    {
        $status = (string) ($invoice['Status'] ?? '');

        return [
            'external_id' => $invoice['InvoiceID'] ?? '',
            'amount' => $invoice['SubTotal'] ?? 0,
            'currency' => $invoice['CurrencyCode'] ?? null,
            'transaction_date' => $this->xeroDate($invoice, 'Date'),
            'is_paid' => $status === 'PAID',
            'paid_at' => $status === 'PAID' ? $this->xeroDate($invoice, 'FullyPaidOnDate') : null,
            'excluded' => $status === 'VOIDED',
            'raw_data' => array_merge($this->commonRaw($invoice), [
                'type' => 'invoice',
                'invoice_number' => $invoice['InvoiceNumber'] ?? null,
                'amount_due' => $invoice['AmountDue'] ?? null,
                'due_date' => $this->xeroDate($invoice, 'DueDate'),
            ]),
        ];
    }

    /**
     * A sales credit note as a negative transaction. It always counts (it is
     * money the customer no longer owes), dated when it was raised; its
     * unallocated remaining credit stands in for amount_due.
     *
     * @param  array<string, mixed>  $creditNote
     * @return array<string, mixed>
     */
    public function mapCreditNote(array $creditNote): array
    {
        $status = (string) ($creditNote['Status'] ?? '');
        $date = $this->xeroDate($creditNote, 'Date');

        return [
            'external_id' => $creditNote['CreditNoteID'] ?? '',
            'amount' => -abs((float) ($creditNote['SubTotal'] ?? 0)),
            'currency' => $creditNote['CurrencyCode'] ?? null,
            'transaction_date' => $date,
            'is_paid' => true,
            'paid_at' => $this->xeroDate($creditNote, 'FullyPaidOnDate') ?? $date,
            'excluded' => $status === 'VOIDED',
            'raw_data' => array_merge($this->commonRaw($creditNote), [
                'type' => 'credit_note',
                'invoice_number' => $creditNote['CreditNoteNumber'] ?? null,
                'amount_due' => $creditNote['RemainingCredit'] ?? null,
            ]),
        ];
    }

    /**
     * Fields shared by invoices and credit notes: customer, reference, status,
     * products, totals and every line-item tracking category (e.g. "Sales
     * Rep" becomes sales_rep; the first option seen for each category wins).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function commonRaw(array $record): array
    {
        $lines = $record['LineItems'] ?? [];
        $itemCodes = collect($lines)->pluck('ItemCode')->filter()->unique()->values();

        $raw = [
            'reference' => $record['Reference'] ?? null,
            'customer' => data_get($record, 'Contact.Name'),
            'contact_id' => data_get($record, 'Contact.ContactID'),
            'status' => (string) ($record['Status'] ?? ''),
            'product' => $itemCodes->first() ?? data_get($lines, '0.Description'),
            'item_codes' => $itemCodes->implode(','),
            'total' => $record['Total'] ?? null,
            'total_tax' => $record['TotalTax'] ?? null,
        ];

        foreach ($lines as $line) {
            foreach ($line['Tracking'] ?? [] as $tracking) {
                $key = Str::snake(Str::lower(trim((string) ($tracking['Name'] ?? ''))));
                if ($key !== '' && ! isset($raw[$key])) {
                    $raw[$key] = $tracking['Option'] ?? null;
                }
            }
        }

        return $raw;
    }

    /**
     * A Xero JSON date field, which comes either as "<Field>String"
     * ("2026-01-15T00:00:00") or in the legacy "/Date(1768435200000+0000)/" form.
     *
     * @param  array<string, mixed>  $record
     */
    protected function xeroDate(array $record, string $field): ?string
    {
        if (! empty($record[$field.'String'])) {
            return substr((string) $record[$field.'String'], 0, 10);
        }

        if (preg_match('#/Date\((-?\d+)#', (string) ($record[$field] ?? ''), $m)) {
            return Carbon::createFromTimestampMs((int) $m[1], 'UTC')->toDateString();
        }

        if (is_string($record[$field] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}/', $record[$field])) {
            return substr($record[$field], 0, 10);
        }

        return null;
    }

    /**
     * Return a valid access token, refreshing it when near expiry.
     *
     * One Xero consent (a "grant") covers every organisation authorised in it,
     * and its refresh token rotates on each use. All sources on the same grant
     * therefore share tokens: a sibling's newer tokens are adopted before
     * refreshing, and refreshed tokens are written to every sibling.
     */
    protected function freshAccessToken(ImportSource $source): string
    {
        $credentials = $source->credentials ?? [];

        if (empty($credentials['refresh_token']) || empty($credentials['tenant_id'])) {
            throw new \RuntimeException('Xero connection is incomplete — reconnect Xero.');
        }

        $siblings = $this->grantSiblings($source);
        $newest = $siblings->map(fn (ImportSource $s) => $s->credentials)
            ->push($credentials)
            ->sortByDesc(fn (array $c) => $c['expires_at'] ?? '')
            ->first();
        $tokens = array_intersect_key($newest, array_flip(['access_token', 'refresh_token', 'expires_at']));

        if ($this->isValid($tokens)) {
            if ($tokens['access_token'] !== $credentials['access_token']) {
                $source->forceFill(['credentials' => array_merge($credentials, $tokens)])->save();
            }

            return $tokens['access_token'];
        }

        $tokens = $this->client->refresh($tokens['refresh_token']);

        foreach ($siblings->push($source) as $grantSource) {
            $grantSource->forceFill(['credentials' => array_merge($grantSource->credentials, $tokens)])->save();
        }

        return $tokens['access_token'];
    }

    /**
     * Other Xero sources in the workspace authorised by the same consent.
     *
     * @return Collection<int, ImportSource>
     */
    protected function grantSiblings(ImportSource $source): Collection
    {
        $grantId = $source->credentials['grant_id'] ?? null;

        if ($grantId === null) {
            return collect();
        }

        return ImportSource::query()
            ->where('workspace_id', $source->workspace_id)
            ->where('type', 'xero')
            ->whereKeyNot($source->getKey())
            ->get()
            ->filter(fn (ImportSource $s) => ($s->credentials['grant_id'] ?? null) === $grantId)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    protected function isValid(array $tokens): bool
    {
        return isset($tokens['expires_at'], $tokens['access_token'])
            && Carbon::parse($tokens['expires_at'])->gt(now()->addSeconds(self::TOKEN_LEEWAY_SECONDS));
    }
}
