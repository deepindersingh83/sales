<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Connectors\Xero\XeroClient;
use App\Services\Connectors\Xero\XeroDriver;
use App\Services\Import\ScheduledImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Credit notes, due/paid dates and customer contacts from Xero, and the
 * customer-balance ageing built on them.
 */
class XeroCustomerDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function source(Workspace $ws, array $config = []): ImportSource
    {
        return ImportSource::create([
            'workspace_id' => $ws->id, 'name' => 'Xero — Demo Co', 'type' => 'xero', 'schedule' => 'daily',
            'config' => array_merge(['tenant_id' => 'T-1', 'tenant_name' => 'Demo Co'], $config),
            'credentials' => ['access_token' => 'access-1', 'refresh_token' => 'refresh-1',
                'expires_at' => now()->addMinutes(30)->toIso8601String(), 'tenant_id' => 'T-1'],
        ]);
    }

    private function fakeXero(array $invoices = [], array $creditNotes = [], array|int $contacts = []): void
    {
        Http::fake([
            XeroClient::INVOICES_URL.'*' => Http::response(['Invoices' => $invoices]),
            XeroClient::CREDIT_NOTES_URL.'*' => Http::response(['CreditNotes' => $creditNotes]),
            XeroClient::CONTACTS_URL.'*' => is_int($contacts)
                ? Http::response(['Title' => 'Forbidden'], $contacts)
                : Http::response(['Contacts' => $contacts]),
        ]);
    }

    private function invoice(string $id, string $status, float $subTotal, float $amountDue, array $extra = []): array
    {
        return array_merge([
            'InvoiceID' => $id, 'InvoiceNumber' => "INV-{$id}", 'Type' => 'ACCREC', 'Status' => $status,
            'Contact' => ['ContactID' => 'C-ACME', 'Name' => 'Acme Pty Ltd'],
            'DateString' => '2026-08-01T00:00:00', 'DueDateString' => '2026-08-31T00:00:00',
            'SubTotal' => $subTotal, 'Total' => $subTotal * 1.1, 'AmountDue' => $amountDue, 'CurrencyCode' => 'AUD',
            'LineItems' => [['ItemCode' => 'PRO', 'Tracking' => [['Name' => 'Sales Rep', 'Option' => 'Alice']]]],
        ], $extra);
    }

    public function test_sales_credit_notes_import_as_negative_transactions(): void
    {
        $ws = Workspace::factory()->create();
        $this->fakeXero(creditNotes: [
            ['CreditNoteID' => 'CN-GUID', 'CreditNoteNumber' => 'CN-0007', 'Type' => 'ACCRECCREDIT', 'Status' => 'AUTHORISED',
                'Contact' => ['ContactID' => 'C-ACME', 'Name' => 'Acme Pty Ltd'], 'DateString' => '2026-09-03T00:00:00',
                'SubTotal' => 200, 'Total' => 220, 'RemainingCredit' => 220, 'CurrencyCode' => 'AUD',
                'LineItems' => [['ItemCode' => 'PRO', 'Tracking' => [['Name' => 'Sales Rep', 'Option' => 'Alice']]]]],
            ['CreditNoteID' => 'CN-DRAFT', 'Type' => 'ACCRECCREDIT', 'Status' => 'DRAFT', 'SubTotal' => 50, 'Total' => 55],
        ]);

        app(ScheduledImportRunner::class)->run($this->source($ws));

        $this->useWorkspace($ws);
        $creditNote = Transaction::sole();
        $this->assertSame('CN-GUID', $creditNote->external_id);
        $this->assertEqualsWithDelta(-200.0, (float) $creditNote->amount, 0.001);
        $this->assertSame('CN-0007', $creditNote->reference());
        $this->assertSame('Alice', $creditNote->raw_data['sales_rep']);   // so the rep's commission is reduced
        $this->assertTrue($creditNote->isCreditNote());
        $this->assertSame('Credit note', $creditNote->paymentStatus());
        $this->assertTrue($creditNote->is_paid);                          // always counts, even on pay-when-paid plans
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'Type=="ACCRECCREDIT"'));
    }

    public function test_invoices_record_due_date_and_the_date_cash_arrived(): void
    {
        $ws = Workspace::factory()->create();
        $this->fakeXero(invoices: [
            $this->invoice('A', 'PAID', 1000, 0, ['FullyPaidOnDate' => '/Date(1788134400000+0000)/']), // 2026-08-31
            $this->invoice('B', 'AUTHORISED', 500, 550),
        ]);

        app(ScheduledImportRunner::class)->run($this->source($ws));

        $this->useWorkspace($ws);
        $paid = Transaction::where('external_id', 'A')->sole();
        $this->assertSame('2026-08-31', $paid->paid_at->toDateString());
        $this->assertSame('2026-08-31', $paid->raw_data['due_date']);
        $this->assertNull(Transaction::where('external_id', 'B')->sole()->paid_at);
    }

    public function test_customer_contacts_sync_into_the_customer_list(): void
    {
        $ws = Workspace::factory()->create();
        $this->fakeXero(contacts: [
            ['ContactID' => 'C-ACME', 'Name' => 'Acme Pty Ltd', 'EmailAddress' => 'ap@acme.test', 'ContactStatus' => 'ACTIVE',
                'Phones' => [['PhoneType' => 'DEFAULT', 'PhoneAreaCode' => '02', 'PhoneNumber' => '9999 0000']]],
            ['ContactID' => 'C-OLD', 'Name' => 'Old Co', 'ContactStatus' => 'ARCHIVED'],
            ['ContactID' => 'C-NONAME', 'Name' => ''],
        ]);
        $source = $this->source($ws);

        app(ScheduledImportRunner::class)->run($source);

        $this->useWorkspace($ws);
        $this->assertSame(['Acme Pty Ltd', 'Old Co'], Customer::orderBy('name')->pluck('name')->all());
        $acme = Customer::where('external_id', 'C-ACME')->sole();
        $this->assertSame('ap@acme.test', $acme->email);
        $this->assertSame('02 9999 0000', $acme->phone);
        $this->assertSame('archived', Customer::where('external_id', 'C-OLD')->sole()->status);
        $this->assertNotNull($source->fresh()->config['contacts_synced_through']);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'IsCustomer==true'));
    }

    public function test_a_contacts_failure_does_not_fail_the_invoice_sync(): void
    {
        $ws = Workspace::factory()->create();
        $this->fakeXero(invoices: [$this->invoice('A', 'AUTHORISED', 100, 110)], contacts: 403);
        $source = $this->source($ws);

        $result = app(ScheduledImportRunner::class)->run($source);

        $this->assertSame(1, $result['created']);
        $this->assertNull($source->fresh()->last_error);
        $this->assertNotNull($source->fresh()->config['contacts_error']);
    }

    public function test_sources_synced_before_this_version_get_one_full_back_fill(): void
    {
        $ws = Workspace::factory()->create();
        $this->fakeXero();
        $source = $this->source($ws, ['synced_through' => '2026-09-01T00:00:00+00:00']); // no sync_version

        app(ScheduledImportRunner::class)->run($source);
        Http::assertNotSent(fn (Request $r) => $r->hasHeader('If-Modified-Since'));
        $this->assertSame(XeroDriver::SYNC_VERSION, $source->fresh()->config['sync_version']);

        app(ScheduledImportRunner::class)->run($source->fresh());
        Http::assertSent(fn (Request $r) => $r->hasHeader('If-Modified-Since'));
    }

    public function test_full_resync_button_reimports_everything(): void
    {
        $ws = Workspace::factory()->create();
        $this->actingAsMember($ws, Role::FullAdmin);
        $this->fakeXero(invoices: [$this->invoice('A', 'AUTHORISED', 100, 110)]);
        $source = $this->source($ws, ['synced_through' => now()->toAtomString(), 'sync_version' => XeroDriver::SYNC_VERSION]);

        $this->post(route('admin.connectors.xero.resync', $source))
            ->assertRedirect(route('admin.connectors.xero.show'))
            ->assertSessionHas('status');

        Http::assertNotSent(fn (Request $r) => $r->hasHeader('If-Modified-Since'));
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, ActivityLog::where('action', 'xero.full_resync')->where('subject_id', $source->id)->count());
    }

    public function test_customer_balances_age_outstanding_amounts_and_net_credit_notes(): void
    {
        $this->travelTo('2026-10-10');
        $ws = Workspace::factory()->create(['base_currency' => 'AUD']);
        $this->actingAsMember($ws, Role::FullAdmin);
        $tx = fn (string $id, float $amount, ?string $due, array $raw = []) => Transaction::factory()->for($ws)->create([
            'external_id' => $id, 'source_system' => 'xero', 'amount' => $amount, 'currency' => 'AUD', 'is_paid' => false,
            'transaction_date' => '2026-06-01', 'profit_amount' => null,
            'raw_data' => array_merge(['customer' => 'Acme', 'due_date' => $due], $raw),
        ]);
        $tx('NOT-DUE', 100, '2026-10-20');
        $tx('LATE-10', 200, '2026-09-30');
        $tx('LATE-45', 300, '2026-08-26');
        $tx('LATE-75', 400, '2026-07-27');
        $tx('LATE-120', 500, '2026-06-12');
        $tx('CREDIT', -50, null, ['type' => 'credit_note', 'total' => 55, 'amount_due' => 55]); // unallocated credit

        $row = collect($this->get(route('admin.reports.customers'))->assertOk()->viewData('rows'))->firstWhere('key', 'Acme');

        $this->assertEquals(['current' => 50.0, 'd1_30' => 200.0, 'd31_60' => 300.0, 'd61_90' => 400.0, 'd90_plus' => 500.0], $row['aging']);
        $this->assertEqualsWithDelta(1450.0, $row['outstanding'], 0.01);
        $this->assertEqualsWithDelta(1400.0, $row['overdue'], 0.01);
    }
}
