<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Flags likely duplicate transactions — the same sale imported twice (e.g. from
 * two systems, or with a shifted date). Groups by amount + transaction date and
 * reports any group with more than one transaction for an admin to review.
 */
class DoublePaymentDetector
{
    /**
     * @return Collection<int, array{amount:float, date:?string, count:int, external_ids:array<int,string>}>
     */
    public function suspects(): Collection
    {
        return Transaction::query()
            ->get(['id', 'external_id', 'amount', 'transaction_date', 'source_system'])
            ->groupBy(fn (Transaction $t) => number_format((float) $t->amount, 2, '.', '').'|'.(optional($t->transaction_date)->toDateString() ?? ''))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => [
                'amount' => (float) $group->first()->amount,
                'date' => optional($group->first()->transaction_date)->toDateString(),
                'count' => $group->count(),
                'external_ids' => $group->pluck('external_id')->all(),
            ])
            ->sortByDesc('count')
            ->values();
    }
}
