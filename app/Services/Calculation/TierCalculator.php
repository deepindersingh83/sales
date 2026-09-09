<?php

namespace App\Services\Calculation;

/**
 * Pure tiered-commission math. No DB, no models — takes plain tier arrays and
 * an attainment figure so it is trivially testable and reproducible from a
 * plan_version snapshot.
 *
 * Rules (user-confirmed, see DECISIONS.md D7):
 *  - rate tier, cumulative     -> marginal: rate applies only to the portion of
 *                                 attainment within [from, to].
 *  - rate tier, non-cumulative -> if attainment is within [from, to], the rate
 *                                 applies to the ENTIRE attainment; else 0.
 *  - amount tier               -> flat cash bonus paid once attainment reaches
 *                                 `from` (bonuses from multiple tiers stack).
 */
class TierCalculator
{
    /**
     * @param  array<int, array{threshold_from:float|int|string, threshold_to:float|int|string|null, kind:string, rate_or_amount:float|int|string, is_cumulative:bool}>  $tiers
     * @return array{total: float, breakdown: array<int, array<string, mixed>>}
     */
    public function commission(array $tiers, float $attainment): array
    {
        $total = 0.0;
        $breakdown = [];

        foreach ($tiers as $tier) {
            $from = (float) $tier['threshold_from'];
            $to = isset($tier['threshold_to']) && $tier['threshold_to'] !== null && $tier['threshold_to'] !== ''
                ? (float) $tier['threshold_to']
                : null;
            $rateOrAmount = (float) $tier['rate_or_amount'];
            $kind = $tier['kind'] ?? 'rate';
            $cumulative = (bool) ($tier['is_cumulative'] ?? true);

            $contribution = 0.0;
            $basis = null;

            if ($kind === 'amount') {
                // Flat bonus once the tier's lower threshold is reached.
                if ($attainment >= $from) {
                    $contribution = $rateOrAmount;
                }
            } elseif ($cumulative) {
                // Progressive/marginal: portion of attainment inside this band.
                $upper = $to ?? INF;
                $basis = max(0.0, min($attainment, $upper) - $from);
                $contribution = $basis * $rateOrAmount;
            } else {
                // Non-cumulative: whole attainment at this tier's rate, but only
                // if attainment falls within the tier's band.
                $withinLower = $attainment >= $from;
                $withinUpper = $to === null || $attainment <= $to;
                if ($withinLower && $withinUpper) {
                    $basis = $attainment;
                    $contribution = $attainment * $rateOrAmount;
                }
            }

            if ($contribution != 0.0 || $basis !== null) {
                $breakdown[] = [
                    'from' => $from,
                    'to' => $to,
                    'kind' => $kind,
                    'cumulative' => $cumulative,
                    'rate_or_amount' => $rateOrAmount,
                    'basis' => $basis,
                    'contribution' => round($contribution, 4),
                ];
            }

            $total += $contribution;
        }

        return [
            'total' => round($total, 4),
            'breakdown' => $breakdown,
        ];
    }
}
