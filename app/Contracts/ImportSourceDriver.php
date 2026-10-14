<?php

namespace App\Contracts;

use App\Models\ImportSource;

/**
 * A source of transactions. CSV is the only MVP implementation; the interface
 * is kept generic so Phase 2 connectors (REST pullers, CRM/ERP integrations,
 * webhooks) can be added without touching the crediting or calculation core.
 *
 * Every driver ultimately yields normalised transaction rows that the
 * TransactionUpserter persists idempotently.
 */
interface ImportSourceDriver
{
    /**
     * Machine key for this driver (matches transactions.source_system), e.g. "csv".
     */
    public function key(): string;

    /**
     * Pull rows from the source and return them as an array of normalised
     * associative rows. Each row MUST contain at least 'external_id' and
     * 'amount', plus a 'raw_data' array of the original record.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function rows(ImportSource $source, array $options = []): iterable;
}
