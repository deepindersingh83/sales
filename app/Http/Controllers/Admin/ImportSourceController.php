<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Services\Import\ColumnMapper;
use App\Services\Import\CsvReader;
use App\Services\Import\ScheduledImportRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Manage recurring import sources: an uploaded CSV plus a cadence
 * (hourly/daily/weekly) that the scheduler re-imports automatically. Admins can
 * also trigger an immediate run.
 */
class ImportSourceController extends Controller
{
    public function __construct(
        protected CsvReader $reader,
        protected ColumnMapper $mapper,
    ) {}

    public function index(): View
    {
        Gate::authorize('import', Transaction::class);

        return view('admin.import-sources.index', [
            'sources' => ImportSource::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'schedule' => ['required', 'in:hourly,daily,weekly'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        // Persist the file so the scheduler can re-read it on each run.
        $token = Str::uuid()->toString();
        Storage::disk('local')->putFileAs('import-sources', $request->file('file'), "{$token}.csv");
        $path = Storage::disk('local')->path("import-sources/{$token}.csv");

        $mapping = $this->mapper->autoDetect($this->reader->headers($path));

        $source = ImportSource::create([
            'name' => $data['name'],
            'type' => 'csv',
            'schedule' => $data['schedule'],
            'source_path' => $path,
            'config' => ['mapping' => $mapping],
            'next_run_at' => now(),
        ]);

        return redirect()->route('admin.import-sources.index')
            ->with('status', "Recurring source “{$source->name}” created ({$source->schedule}).");
    }

    public function run(ImportSource $source, ScheduledImportRunner $runner): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        try {
            $result = $runner->run($source);
            $status = "Ran “{$source->name}” — {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";
        } catch (\Throwable $e) {
            return back()->withErrors(['run' => $e->getMessage()]);
        }

        return redirect()->route('admin.import-sources.index')->with('status', $status);
    }

    public function destroy(ImportSource $source): RedirectResponse
    {
        Gate::authorize('import', Transaction::class);

        if ($source->source_path && str_contains($source->source_path, 'import-sources')) {
            @unlink($source->source_path);
        }
        $source->delete();

        return redirect()->route('admin.import-sources.index')->with('status', 'Recurring source removed.');
    }
}
