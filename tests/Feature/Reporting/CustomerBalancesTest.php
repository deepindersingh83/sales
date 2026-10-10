<?php

namespace Tests\Feature\Reporting;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBalancesTest extends TestCase
{
    use RefreshDatabase;

    /** Acme: one paid 1,000 invoice and one 500 invoice half paid (Xero amount due 275 of 550 incl. GST). Globex: unpaid 200. */
    private function seedInvoices(Workspace $ws): void
    {
        $invoice = fn (string $number, string $customer, float $amount, bool $paid, array $raw = []) => Transaction::factory()->for($ws)->create([
            'external_id' => "guid-{$number}", 'source_system' => 'xero', 'amount' => $amount, 'profit_amount' => null,
            'currency' => 'AUD', 'is_paid' => $paid, 'transaction_date' => '2026-09-15',
            'raw_data' => array_merge(['invoice_number' => $number, 'customer' => $customer], $raw),
        ]);

        $invoice('INV-001', 'Acme Pty Ltd', 1000, true, ['total' => 1100, 'amount_due' => 0]);
        $invoice('INV-002', 'Acme Pty Ltd', 500, false, ['total' => 550, 'amount_due' => 275]);
        $invoice('INV-003', 'Globex', 200, false);
        $invoice('INV-004', 'Globex', 9999, false)->update(['excluded' => true]);
    }

    public function test_customer_balances_split_paid_and_outstanding_per_customer(): void
    {
        $ws = Workspace::factory()->create(['base_currency' => 'AUD']);
        $this->seedInvoices($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $response = $this->get(route('admin.reports.customers'))->assertOk();

        $rows = collect($response->viewData('rows'))->keyBy('key');
        $this->assertEqualsWithDelta(1500.0, $rows['Acme Pty Ltd']['revenue'], 0.01);
        $this->assertEqualsWithDelta(1250.0, $rows['Acme Pty Ltd']['paid'], 0.01);        // part payment counted
        $this->assertEqualsWithDelta(250.0, $rows['Acme Pty Ltd']['outstanding'], 0.01);
        $this->assertEqualsWithDelta(200.0, $rows['Globex']['outstanding'], 0.01);         // excluded 9,999 ignored
        $this->assertSame('Acme Pty Ltd', $response->viewData('rows')[0]['key']);          // sorted by outstanding
        $response->assertSee(route('admin.transactions.index', ['customer' => 'Acme Pty Ltd']), false);

        $csv = $this->get(route('admin.reports.customers', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('"Acme Pty Ltd",2,1500,1250,250', $csv);
    }

    public function test_transactions_show_invoice_numbers_and_filter_by_customer_with_totals(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedInvoices($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertSee('INV-001')
            ->assertSee('Part-paid');

        $this->get(route('admin.transactions.index', ['customer' => 'Acme Pty Ltd']))
            ->assertOk()
            ->assertSee('INV-002')
            ->assertDontSee('INV-003')
            ->assertSee('1,250.00')   // paid
            ->assertSee('250.00');    // outstanding
    }

    public function test_transactions_search_and_status_filter(): void
    {
        $ws = Workspace::factory()->create();
        $this->seedInvoices($ws);
        $this->actingAsMember($ws, Role::FullAdmin);

        $this->get(route('admin.transactions.index', ['q' => 'INV-003']))->assertSee('Globex')->assertDontSee('INV-001');
        $this->get(route('admin.transactions.index', ['q' => 'acme']))->assertSee('INV-002')->assertDontSee('INV-003');
        $this->get(route('admin.transactions.index', ['status' => 'paid']))->assertSee('INV-001')->assertDontSee('INV-002');
        $this->get(route('admin.transactions.index', ['q' => ['x']]))->assertOk();
    }
}
