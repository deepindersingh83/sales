<?php

namespace Tests\Feature\Import;

use App\Models\ImportSource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Import\CsvReader;
use App\Services\Import\ScheduledImportRunner;
use App\Services\Import\TransactionUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvParsingTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function tearDown(): void
    {
        if (isset($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    private function importFile(Workspace $ws, string $contents): array
    {
        $this->path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($this->path, $contents);
        $source = ImportSource::create(['workspace_id' => $ws->id, 'name' => 'Feed', 'type' => 'csv',
            'schedule' => 'daily', 'source_path' => $this->path]);

        $result = app(ScheduledImportRunner::class)->run($source);
        $this->useWorkspace($ws);

        return $result;
    }

    public function test_excel_european_export_with_bom_semicolons_and_latin1_imports_cleanly(): void
    {
        $ws = Workspace::factory()->create(['base_currency' => 'EUR']);
        $csv = "\xEF\xBB\xBFinvoice_id;amount;date;customer\n"
            ."A-1;1.234,50;03/04/2026;Caf\xE9 Paris\n";   // Latin-1 é in an otherwise UTF-8 file

        $result = $this->importFile($ws, $csv);

        $this->assertSame(1, $result['created']);
        $tx = Transaction::sole();
        $this->assertSame('A-1', $tx->external_id);
        $this->assertEqualsWithDelta(1234.50, (float) $tx->amount, 0.001);
        $this->assertSame('2026-04-03', $tx->transaction_date->toDateString()); // day first
        $this->assertSame('EUR', $tx->currency);                                 // workspace base, not USD
        $this->assertSame('Café Paris', $tx->raw_data['customer']);
        $this->assertArrayHasKey('invoice_id', $tx->raw_data);                   // BOM stripped from header
    }

    public function test_amount_formats(): void
    {
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);
        $expected = [
            '(100.00)' => -100.0, '100-' => -100.0, '-$1,234.50' => -1234.5, '$1,234.50' => 1234.5,
            '1,234' => 1234.0, '12,5' => 12.5, '1.234.567' => 1234567.0, '99.95' => 99.95,
        ];
        $raws = array_keys($expected);

        app(TransactionUpserter::class)->upsert(
            array_map(fn ($i) => ['external_id' => "T{$i}", 'amount' => $raws[$i]], array_keys($raws)),
            'csv',
        );

        foreach ($raws as $i => $raw) {
            $stored = (float) Transaction::where('external_id', "T{$i}")->value('amount');
            $this->assertEqualsWithDelta($expected[$raw], $stored, 0.001, "Parsing {$raw}");
        }
    }

    public function test_unambiguous_and_unreadable_dates(): void
    {
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);

        $result = app(TransactionUpserter::class)->upsert([
            ['external_id' => 'US', 'amount' => 1, 'transaction_date' => '04/13/2026'],  // month first, unambiguous
            ['external_id' => 'ISO', 'amount' => 1, 'transaction_date' => '2026-04-13T09:00:00Z'],
            ['external_id' => 'WORD', 'amount' => 1, 'transaction_date' => '13 Apr 2026'],
            ['external_id' => 'BLANK', 'amount' => 1, 'transaction_date' => ''],
            ['external_id' => 'BAD', 'amount' => 1, 'transaction_date' => '31/02/2026'],
            ['external_id' => 'JUNK', 'amount' => 1, 'transaction_date' => '1e3'],
        ], 'csv');

        $this->assertSame(['created' => 4, 'updated' => 0, 'skipped' => 2], $result);
        $dates = Transaction::get()->mapWithKeys(fn ($t) => [$t->external_id => $t->transaction_date?->toDateString()]);
        $this->assertSame('2026-04-13', $dates['US']);
        $this->assertSame('2026-04-13', $dates['ISO']);
        $this->assertSame('2026-04-13', $dates['WORD']);
        $this->assertNull($dates['BLANK']);
    }

    public function test_us_date_order_can_be_configured(): void
    {
        config(['app.import_date_order' => 'mdy']);
        $ws = Workspace::factory()->create();
        $this->useWorkspace($ws);

        app(TransactionUpserter::class)->upsert([['external_id' => 'A', 'amount' => 1, 'transaction_date' => '03/04/2026']], 'csv');

        $this->assertSame('2026-03-04', Transaction::sole()->transaction_date->toDateString());
    }

    public function test_headers_read_the_same_way_as_rows(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($this->path, "\xEF\xBB\xBFrep;amount\nAlice;10\n");

        $this->assertSame(['rep', 'amount'], app(CsvReader::class)->headers($this->path));
        $this->assertSame([['rep' => 'Alice', 'amount' => '10']], app(CsvReader::class)->rows($this->path));
    }
}
