<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Credit;
use Illuminate\Support\Collection;

/**
 * Ranks reps by released credited attainment — overall, or within a contest's
 * date window (by transaction date).
 */
class LeaderboardService
{
    /**
     * @return Collection<int, array{rank:int, user:string, total:float}>
     */
    public function standings(?Contest $contest = null): Collection
    {
        $query = Credit::released()->with(['user', 'transaction']);

        $rows = $query->get();

        if ($contest && $contest->starts_on && $contest->ends_on) {
            $rows = $rows->filter(function (Credit $c) use ($contest) {
                $date = $c->transaction?->transaction_date;

                return $date && $date->betweenIncluded($contest->starts_on, $contest->ends_on);
            });
        }

        return $rows->groupBy('user_id')
            ->map(fn ($group) => [
                'user' => $group->first()->user?->name ?? 'Unknown',
                'total' => round((float) $group->sum('credited_amount'), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->map(function ($row, $i) {
                $row['rank'] = $i + 1;

                return $row;
            });
    }
}
