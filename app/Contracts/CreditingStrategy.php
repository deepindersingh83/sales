<?php

namespace App\Contracts;

use App\Models\Alias;
use App\Models\Transaction;

/**
 * Decides which user a transaction is credited to. Alias matching is the MVP
 * strategy; the interface stays generic so team-splitting, hierarchy rollups,
 * or CRM-owner strategies can be added later without changing the engine.
 */
interface CreditingStrategy
{
    public function key(): string;

    /**
     * Resolve the credited user id for a transaction, or null if none matched.
     *
     * @param  array<int, Alias>|null  $context  pre-loaded matching context
     */
    public function creditUserId(Transaction $transaction): ?int;
}
