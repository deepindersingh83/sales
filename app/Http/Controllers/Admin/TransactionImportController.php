<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Services\Import\ColumnMapper;
use App\Services\Import\CsvReader;
use App\Services\Import\TransactionUpserter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransactionImportController extends Controller
{
    public function __construct(
        protected CsvReader $reader,
        protected ColumnMapper $mapper,
        protected TransactionUpserter $upserter,
    ) {}

    /** Step 1 — the upload form. */
    public function create(): View
    {
        Gate::authorize('import', Transaction::class);

        return view('admin.imports.create');
    }

    /** Step 2 — parse, auto-detect mapping, show the mapping form. */
    public function preview(Request $request): View|RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        // Persist the upload to a per-workspace temp path so we don't need a
        // re-upload at the confirm step.
        $token = Str::uuid()->toString();
        $path = "imports/{$token}.csv";
        Storage::disk('local')->putFileAs('imports', $request->file('file'), "{$token}.csv");

        $full = Storage::disk('local')->path($path);
        $headers = $this->reader->headers($full);

        if (empty($headers)) {
            Storage::disk('local')->delete($path);

            return redirect()->route('admin.imports.create')
                ->withErrors(['file' => 'Could not read any columns from that file.']);
        }

        return view('admin.imports.preview', [
            'token' => $token,
            'headers' => $headers,
            'sample' => $this->reader->rows($full, 5),
            'mapping' => $this->mapper->autoDetect($headers),
        ]);
    }

    /** Step 3 — apply confirmed mapping and idempotently upsert. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'source_name' => ['required', 'string', 'max:255'],
            'mapping' => ['required', 'array'],
            'mapping.external_id' => ['required', 'string'],
            'mapping.amount' => ['required', 'string'],
        ]);

        $path = 'imports/'.basename($validated['token']).'.csv';
        if (! Storage::disk('local')->exists($path)) {
            return redirect()->route('admin.imports.create')
                ->withErrors(['file' => 'The uploaded file expired — please upload again.']);
        }

        $source = ImportSource::firstOrCreate(
            ['name' => $validated['source_name'], 'type' => 'csv'],
            ['config' => ['mapping' => $validated['mapping']]],
        );

        $full = Storage::disk('local')->path($path);
        $mapping = $validated['mapping'];

        $rows = array_map(
            fn (array $row) => $this->reader->applyMapping($row, $mapping),
            $this->reader->rows($full),
        );

        $result = $this->upserter->upsert($rows, 'csv', $source);

        $source->update(['last_synced_at' => now(), 'config' => ['mapping' => $mapping]]);
        Storage::disk('local')->delete($path);

        return redirect()->route('admin.transactions.index')->with(
            'status',
            "Import complete — {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped."
        );
    }
}
