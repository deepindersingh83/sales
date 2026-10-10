<?php

namespace App\Services;

use App\Enums\RewardType;
use App\Models\Contest;
use App\Models\Credit;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ranks reps overall (released credited attainment) or within a contest, by
 * the contest's metric: "credited" (released credits, windowed by transaction
 * date) or "payout" (released cash rewards, windowed by the date they were
 * earned). Either end of a contest window may be open. Aggregated in SQL.
 */
class LeaderboardService
{
    /**
     * @return Collection<int, array{rank:int, user:string, total:float}>
     */
    public function standings(?Contest $contest = null): Collection
    {
        [$query, $column] = $contest?->metric === 'payout'
            ? [$this->payoutQuery($contest), 'computed_amount']
            : [$this->creditedQuery($contest), 'credited_amount'];

        $totals = $query->selectRaw("user_id, SUM({$column}) as total")
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $names = User::whereIn('id', $totals->keys())->pluck('name', 'id');

        return $totals
            ->map(fn ($total, $userId) => [
                'user' => $names[$userId] ?? 'Unknown',
                'total' => round((float) $total, 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->map(function ($row, $i) {
                $row['rank'] = $i + 1;

                return $row;
            });
    }

    protected function creditedQuery(?Contest $contest): Builder
    {
        return Credit::released()
            ->when($contest?->starts_on, fn (Builder $q, $from) => $q->whereHas('transaction',
                fn (Builder $t) => $t->whereDate('transaction_date', '>=', $from->toDateString())))
            ->when($contest?->ends_on, fn (Builder $q, $to) => $q->whereHas('transaction',
                fn (Builder $t) => $t->whereDate('transaction_date', '<=', $to->toDateString())));
    }

    protected function payoutQuery(Contest $contest): Builder
    {
        $cashTypes = collect(RewardType::cases())->filter->isCash()->map->value->values()->all();

        return Reward::released()
            ->whereIn('reward_type', $cashTypes)
            ->whereNotNull('computed_amount')
            ->when($contest->starts_on, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when($contest->ends_on, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to->toDateString()));
    }
}
