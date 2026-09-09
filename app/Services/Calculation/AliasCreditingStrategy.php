<?php

namespace App\Services\Calculation;

use App\Contracts\CreditingStrategy;
use App\Models\Alias;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Credits each transaction to the FIRST alias (by id) that matches it.
 * Aliases are loaded once for the current workspace and reused across all
 * transactions in a run.
 */
class AliasCreditingStrategy implements CreditingStrategy
{
    /** @var Collection<int, Alias>|null */
    protected ?Collection $aliases = null;

    public function key(): string
    {
        return 'alias';
    }

    /**
     * Preload the workspace's aliases (call once before a run for efficiency).
     */
    public function warm(): void
    {
        $this->aliases = Alias::orderBy('id')->get();
    }

    public function creditUserId(Transaction $transaction): ?int
    {
        if ($this->aliases === null) {
            $this->warm();
        }

        foreach ($this->aliases as $alias) {
            if ($alias->user_id !== null && $alias->matches($transaction)) {
                return $alias->user_id;
            }
        }

        return null;
    }

    /**
     * All credit allocations for a transaction. A single matching alias credits
     * 100% (unless its split_percent says otherwise); several matching aliases
     * split the transaction by their split_percent (deal splits).
     *
     * @return array<int, array{user_id:int, fraction:float}>
     */
    public function allocations(Transaction $transaction): array
    {
        if ($this->aliases === null) {
            $this->warm();
        }

        $allocations = [];
        foreach ($this->aliases as $alias) {
            if ($alias->user_id !== null && $alias->matches($transaction)) {
                $allocations[] = [
                    'user_id' => $alias->user_id,
                    'fraction' => ((float) $alias->split_percent) / 100.0,
                ];
            }
        }

        return $allocations;
    }
}
