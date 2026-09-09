<?php

namespace App\Services\Import;

use App\Models\ImportSource;
use App\Support\WorkspaceContext;

/**
 * Executes a stored ImportSource: reads its CSV from the configured path,
 * applies the saved column mapping, and upserts transactions. Used by both the
 * scheduler command and the "run now" admin action. Sets the source's
 * last_synced_at / next_run_at bookkeeping.
 */
class ScheduledImportRunner
{
    public function __construct(
        protected CsvReader $reader,
        protected ColumnMapper $mapper,
        protected TransactionUpserter $upserter,
        protected WorkspaceContext $context,
    ) {}

    /**
     * @return array{created:int, updated:int, skipped:int}
     */
    public function run(ImportSource $source): array
    {
        $path = $source->source_path;

        if (! $path || ! is_readable($path)) {
            throw new \RuntimeException("Import source file is missing or unreadable: {$path}");
        }

        // The runner may execute outside an HTTP request (queue/scheduler), so
        // bind the workspace context to the source's tenant for the duration.
        return $this->context->runAs($source->workspace_id, function () use ($source, $path) {
            $mapping = $source->config['mapping'] ?? $this->mapper->autoDetect($this->reader->headers($path));

            $rows = array_map(
                fn (array $row) => $this->reader->applyMapping($row, $mapping),
                $this->reader->rows($path),
            );

            $result = $this->upserter->upsert($rows, $source->type ?: 'csv', $source);

            $now = now();
            $source->forceFill([
                'last_synced_at' => $now,
                'next_run_at' => $source->computeNextRunAt($now),
                'config' => array_merge($source->config ?? [], ['mapping' => $mapping]),
            ])->save();

            return $result;
        });
    }
}
