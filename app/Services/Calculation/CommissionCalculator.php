<?php

namespace App\Services\Calculation;

/**
 * One rep's commission for a plan configuration and attainment totals: the
 * plan's custom formula when it has one, otherwise tier math, then the payout
 * cap. Pure — no logging, no persistence — so the calculation engine and the
 * commission estimator produce identical numbers.
 */
class CommissionCalculator
{
    public function __construct(
        protected TierCalculator $tierCalculator,
        protected FormulaEvaluator $formula,
    ) {}

    /**
     * `total` is the payout after the cap; `meta` describes the pre-cap
     * calculation (method, attainment, quota, tier breakdown or formula).
     *
     * @param  array<string, mixed>  $plan  the snapshot's "plan" section
     * @param  array<int, array<string, mixed>>  $tiers  the snapshot's tiers
     * @param  array{attainment: float, revenue: float, profit: float}  $totals
     * @return array{total: float, uncapped: float, capped: bool, cap: float|null, meta: array<string, mixed>}
     */
    public function calculate(array $plan, array $tiers, array $totals): array
    {
        $quota = isset($plan['quota']) ? (float) $plan['quota'] : null;
        $attainmentPct = ($quota && $quota > 0) ? $totals['attainment'] / $quota : 0.0;

        $meta = [
            'attainment' => $totals['attainment'],
            'quota' => $quota,
            'attainment_pct' => round($attainmentPct, 6),
        ];

        $formula = $plan['commission_formula'] ?? null;

        if (! empty($formula)) {
            $total = $this->formula->evaluate($formula, [
                'attainment' => $totals['attainment'],
                'revenue' => $totals['revenue'],
                'profit' => $totals['profit'],
                'quota' => $quota ?? 0.0,
                'attainment_pct' => $attainmentPct,
                'rate' => 0.0,
            ]);
            $meta['method'] = 'formula';
            $meta['formula'] = $formula;
        } else {
            $result = $this->tierCalculator->commission($tiers, $totals['attainment']);
            $total = $result['total'];
            $meta['method'] = 'tiers';
            $meta['breakdown'] = $result['breakdown'];
        }

        $cap = isset($plan['payout_cap']) ? (float) $plan['payout_cap'] : null;
        $capped = $cap !== null && $total > $cap;

        return [
            'total' => $capped ? $cap : $total,
            'uncapped' => $total,
            'capped' => $capped,
            'cap' => $cap,
            'meta' => $meta,
        ];
    }
}
