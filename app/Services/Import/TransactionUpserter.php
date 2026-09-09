<?php

namespace App\Services\Import;

use App\Models\ImportSource;
use App\Models\Transaction;
use App\Support\WorkspaceContext;
use Illuminate\Support\Carbon;

/**
 * Persists normalised transaction rows idempotently. Re-importing the same
 * data updates the existing rows (matched on workspace + source_system +
 * external_id) instead of inserting duplicates.
 */
class TransactionUpserter
{
    public function __construct(protected WorkspaceContext $context) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return array{created:int, updated:int, skipped:int}
     */
    public function upsert(iterable $rows, string $sourceSystem, ?ImportSource $source = null): array
    {
        $workspaceId = $this->context->id();
        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            $externalId = trim((string) ($row['external_id'] ?? ''));

            // A row with no stable external id cannot be deduplicated — skip it
            // rather than risk duplicate inserts on re-import.
            if ($externalId === '') {
                $skipped++;

                continue;
            }

            $existing = Transaction::where('source_system', $sourceSystem)
                ->where('external_id', $externalId)
                ->first();

            $attributes = [
                'workspace_id' => $workspaceId,
                'import_source_id' => $source?->id,
                'external_id' => $externalId,
                'source_system' => $sourceSystem,
                'raw_data' => $row['raw_data'] ?? [],
                'amount' => $this->toDecimal($row['amount'] ?? 0),
                'profit_amount' => isset($row['profit_amount']) && $row['profit_amount'] !== ''
                    ? $this->toDecimal($row['profit_amount'])
                    : null,
                'currency' => strtoupper((string) ($row['currency'] ?? 'USD')) ?: 'USD',
                'transaction_date' => $this->toDate($row['transaction_date'] ?? null),
            ];

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                Transaction::create($attributes);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    protected function toDecimal(mixed $value): float
    {
        if (is_string($value)) {
            // Strip currency symbols, thousands separators, spaces.
            $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '0';
        }

        return (float) $value;
    }

    protected function toDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
