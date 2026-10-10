<?php

namespace App\Services\Import;

use App\Models\ImportSource;
use App\Services\Connectors\Xero\XeroDriver;
use App\Support\WorkspaceContext;

/**
 * Executes a stored ImportSource: either re-reads its CSV (applying the saved
 * column mapping) or pulls from its live connector (Xero), then upserts
 * transactions. Used by both the scheduler command and the "run now" admin
 * action. Sets the source's last_synced_at / next_run_at / last_error
 * bookkeeping.
 */
class ScheduledImportRunner
{
    public function __construct(
        protected CsvReader $reader,
        protected ColumnMapper $mapper,
        protected TransactionUpserter $upserter,
        protected WorkspaceContext $context,
        protected XeroDriver $xero,
    ) {}

    /**
     * @return array{created:int, updated:int, skipped:int}
     */
    public function run(ImportSource $source): array
    {
        // The runner may execute outside an HTTP request (queue/scheduler), so
        // bind the workspace context to the source's tenant for the duration.
        return $this->context->runAs($source->workspace_id, function () use ($source) {
            $startedAt = now();

            try {
                [$rows, $config] = $source->isConnector()
                    ? $this->connectorRows($source, $startedAt)
                    : $this->csvRows($source);

                $result = $this->upserter->upsert($rows, $source->type ?: 'csv', $source);
            } catch (\Throwable $e) {
                $source->forceFill(['last_error' => $e->getMessage()])->save();

                throw $e;
            }

            // Rows arrived but none could be stored (e.g. no external_id
            // column was detected): surface it instead of a silent "success".
            $allSkipped = $result['skipped'] > 0 && $result['created'] + $result['updated'] === 0;

            $source->forceFill([
                'last_synced_at' => $startedAt,
                'last_error' => $allSkipped
                    ? "All {$result['skipped']} rows were skipped — check the file has an external ID column."
                    : null,
                'next_run_at' => $source->computeNextRunAt($startedAt),
                'config' => $config,
            ])->save();

            return $result;
        });
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    protected function csvRows(ImportSource $source): array
    {
        $path = $source->source_path;

        if (! $path || ! is_readable($path)) {
            throw new \RuntimeException("Import source file is missing or unreadable: {$path}");
        }

        $mapping = $source->config['mapping'] ?? $this->mapper->autoDetect($this->reader->headers($path));

        $rows = array_map(
            fn (array $row) => $this->reader->applyMapping($row, $mapping),
            $this->reader->rows($path),
        );

        return [$rows, array_merge($source->config ?? [], ['mapping' => $mapping])];
    }

    /**
     * Pull from the live connector. The sync cursor only advances once the
     * pull succeeded, so a failed run is retried from the same point.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    protected function connectorRows(ImportSource $source, \DateTimeInterface $startedAt): array
    {
        $rows = $this->xero->rows($source);

        return [$rows, array_merge($source->config ?? [], ['synced_through' => $startedAt->format(DATE_ATOM)])];
    }
}
