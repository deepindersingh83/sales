<?php

namespace Tests\Feature\Import;

use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Import\ColumnMapper;
use App\Services\Import\CsvReader;
use App\Services\Import\TransactionUpserter;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function writeCsv(string $contents): string
    {
        // Build a unique path directly rather than via tempnam(), which emits a
        // warning under PHP 8.4 when the file lands in the system temp dir.
        $path = sys_get_temp_dir().'/csv_import_test_'.bin2hex(random_bytes(8)).'.csv';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_auto_detects_common_columns(): void
    {
        $mapping = app(ColumnMapper::class)->autoDetect(
            ['Deal ID', 'Sales Amount', 'Gross Profit', 'Currency', 'Close Date', 'Rep']
        );

        $this->assertSame('Deal ID', $mapping['external_id']);
        $this->assertSame('Sales Amount', $mapping['amount']);
        $this->assertSame('Gross Profit', $mapping['profit_amount']);
        $this->assertSame('Currency', $mapping['currency']);
        $this->assertSame('Close Date', $mapping['transaction_date']);
    }

    public function test_import_is_idempotent_on_reimport(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);

        $csv = $this->writeCsv(
            "Deal ID,Sales Amount,Gross Profit,Currency,Close Date,Rep\n".
            "D-1,1000,400,usd,2026-01-15,Alice\n".
            "D-2,2500,900,USD,2026-01-20,Bob\n"
        );

        $reader = app(CsvReader::class);
        $mapper = app(ColumnMapper::class);
        $upserter = app(TransactionUpserter::class);
        $mapping = $mapper->autoDetect($reader->headers($csv));

        $rows = array_map(fn ($r) => $reader->applyMapping($r, $mapping), $reader->rows($csv));

        $first = $upserter->upsert($rows, 'csv');
        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $first);
        $this->assertSame(2, Transaction::count());

        // Re-import the exact same file: no new rows, both updated.
        $second = $upserter->upsert($rows, 'csv');
        $this->assertSame(['created' => 0, 'updated' => 2, 'skipped' => 0], $second);
        $this->assertSame(2, Transaction::count(), 'Re-import must not create duplicates.');

        // Values normalised (currency upper-cased, amount numeric).
        $d1 = Transaction::where('external_id', 'D-1')->first();
        $this->assertSame('USD', $d1->currency);
        $this->assertEqualsWithDelta(1000.0, (float) $d1->amount, 0.001);
        $this->assertEqualsWithDelta(400.0, (float) $d1->profit_amount, 0.001);
        $this->assertSame('Alice', $d1->raw_data['Rep']);
    }

    public function test_changed_values_update_in_place(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $reader = app(CsvReader::class);
        $upserter = app(TransactionUpserter::class);
        $mapping = ['external_id' => 'id', 'amount' => 'amt'];

        $csv1 = $this->writeCsv("id,amt\nD-9,100\n");
        $upserter->upsert(array_map(fn ($r) => $reader->applyMapping($r, $mapping), $reader->rows($csv1)), 'csv');

        $csv2 = $this->writeCsv("id,amt\nD-9,175\n");
        $upserter->upsert(array_map(fn ($r) => $reader->applyMapping($r, $mapping), $reader->rows($csv2)), 'csv');

        $this->assertSame(1, Transaction::count());
        $this->assertEqualsWithDelta(175.0, (float) Transaction::first()->amount, 0.001);
    }

    public function test_rows_without_external_id_are_skipped(): void
    {
        $ws = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($ws);
        $reader = app(CsvReader::class);
        $upserter = app(TransactionUpserter::class);

        $csv = $this->writeCsv("id,amt\n,500\nD-3,600\n");
        $result = $upserter->upsert(
            array_map(fn ($r) => $reader->applyMapping($r, ['external_id' => 'id', 'amount' => 'amt']), $reader->rows($csv)),
            'csv'
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Transaction::count());
    }
}
