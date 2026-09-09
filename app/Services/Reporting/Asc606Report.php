<?php

namespace App\Services\Reporting;

use App\Models\Reward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ASC 606 straight-line commission amortization: a released commission is a cost
 * of obtaining a contract and is recognised over the contract's benefit period
 * rather than all at once. This spreads each released cash reward evenly across
 * `months` periods starting the month it was earned, and rolls the recognised
 * expense up by month.
 */
class Asc606Report
{
    /**
     * @return array{by_month: Collection<string, float>, total: float, months: int}
     */
    public function schedule(int $months = 12): array
    {
        $months = max(1, $months);
        $byMonth = [];
        $total = 0.0;

        Reward::released()
            ->whereNotNull('computed_amount')
            ->get(['computed_amount', 'created_at'])
            ->each(function (Reward $r) use ($months, &$byMonth, &$total) {
                $amount = (float) $r->computed_amount;
                $total += $amount;
                $perMonth = $amount / $months;
                $start = Carbon::parse($r->created_at)->startOfMonth();

                for ($i = 0; $i < $months; $i++) {
                    $key = $start->copy()->addMonths($i)->format('Y-m');
                    $byMonth[$key] = ($byMonth[$key] ?? 0.0) + $perMonth;
                }
            });

        ksort($byMonth);

        return [
            'by_month' => collect($byMonth)->map(fn ($v) => round($v, 2)),
            'total' => round($total, 2),
            'months' => $months,
        ];
    }
}
