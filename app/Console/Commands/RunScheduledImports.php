<?php

namespace App\Console\Commands;

use App\Models\ImportSource;
use App\Services\Import\ScheduledImportRunner;
use Illuminate\Console\Command;

/**
 * Processes every recurring import source that is due, across all workspaces.
 * Registered on the scheduler to run hourly; also runnable on demand.
 */
class RunScheduledImports extends Command
{
    protected $signature = 'imports:run-scheduled {--force : Run every scheduled source regardless of its due time}';

    protected $description = 'Run recurring transaction imports that are due';

    public function handle(ScheduledImportRunner $runner): int
    {
        $sources = ImportSource::acrossAllWorkspaces()
            ->whereIn('schedule', ['hourly', 'daily', 'weekly'])
            ->whereNotNull('source_path')
            ->get();

        $ran = 0;

        foreach ($sources as $source) {
            if (! $this->option('force') && ! $source->isDue()) {
                continue;
            }

            try {
                $result = $runner->run($source);
                $ran++;
                $this->info("[{$source->name}] created {$result['created']}, updated {$result['updated']}, skipped {$result['skipped']}.");
            } catch (\Throwable $e) {
                $this->error("[{$source->name}] failed: {$e->getMessage()}");
            }
        }

        $this->info("Scheduled imports complete — {$ran} source(s) processed.");

        return self::SUCCESS;
    }
}
