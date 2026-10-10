<?php

namespace App\Services\Calculation;

use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "What if I close this deal?" for one rep on one plan.
 *
 * Current attainment is the rep's RELEASED credits on this plan's runs whose
 * transactions fall in the plan's current period — the same window the
 * calculation engine credits (fixed start/end dates, or quarter/year to date
 * for qtd/ytd plans; no window when the plan has no dates). The hypothetical
 * deal is a value in the plan's performance metric and currency, added to
 * attainment and to that metric's total (revenue or profit).
 *
 * Commission comes from the same CommissionCalculator the engine uses on the
 * plan's live configuration (what the next run would snapshot), so formulas,
 * tiers and the payout cap all match. Reward rules and manager overrides are
 * not part of the estimate.
 */
class CommissionEstimator
{
    public function __construct(
        protected PlanSnapshot $snapshots,
        protected CommissionCalculator $calculator,
        protected TierCalculator $tierCalculator,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     period: array{0: CarbonImmutable|null, 1: CarbonImmutable|null},
     *     quota: float|null,
     *     deal_amount: float,
     *     current: array{attainment: float, attainment_pct: float|null, commission: float, capped: bool, tier: array{number: int, from: float, to: float|null, kind: string, rate_or_amount: float}|null},
     *     with_deal: array{attainment: float, attainment_pct: float|null, commission: float, capped: bool, tier: array{number: int, from: float, to: float|null, kind: string, rate_or_amount: float}|null},
     *     difference: float
     * }
     */
    public function estimate(User $user, Plan $plan, float $dealAmount): array
    {
        $config = $this->snapshots->describe($plan);
        $metric = $config['plan']['performance_metric'] ?? 'revenue';
        $period = $this->period($config['plan']);

        $current = $this->currentTotals($user, $plan, $period);
        $withDeal = [
            'attainment' => $current['attainment'] + $dealAmount,
            'revenue' => $current['revenue'] + ($metric === 'profit' ? 0.0 : $dealAmount),
            'profit' => $current['profit'] + ($metric === 'profit' ? $dealAmount : 0.0),
        ];

        $currentResult = $this->outcome($config, $current);
        $dealResult = $this->outcome($config, $withDeal);

        return [
            'currency' => (string) ($config['plan']['currency'] ?? 'USD'),
            'period' => $period,
            'quota' => $config['plan']['quota'] ?? null,
            'deal_amount' => $dealAmount,
            'current' => $currentResult,
            'with_deal' => $dealResult,
            'difference' => round($dealResult['commission'] - $currentResult['commission'], 4),
        ];
    }

    /**
     * The plan's current crediting window, mirroring CalculationEngine:
     * quarter/year to date for qtd/ytd plans, else the plan's own dates.
     * [null, null] when the plan is unbounded.
     *
     * @param  array<string, mixed>  $plan  the snapshot's "plan" section
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public function period(array $plan): array
    {
        $now = CarbonImmutable::now();

        [$start, $end] = match ($plan['period_type'] ?? null) {
            'qtd' => [$now->firstOfQuarter()->toDateString(), $now->toDateString()],
            'ytd' => [$now->startOfYear()->toDateString(), $now->toDateString()],
            default => [$plan['start_date'] ?? null, $plan['end_date'] ?? null],
        };

        if (! $start || ! $end) {
            return [null, null];
        }

        return [CarbonImmutable::parse($start), CarbonImmutable::parse($end)];
    }

    /**
     * Released attainment, revenue and profit credited to the rep on this
     * plan in the period. Revenue and profit are each credit's share of its
     * transaction, so FX, tax and split fractions carry over.
     *
     * @param  array{0: CarbonImmutable|null, 1: CarbonImmutable|null}  $period
     * @return array{attainment: float, revenue: float, profit: float}
     */
    protected function currentTotals(User $user, Plan $plan, array $period): array
    {
        [$start, $end] = $period;
        $metric = $plan->performance_metric ?? 'revenue';

        $credits = Credit::released()
            ->where('user_id', $user->id)
            ->whereIn('calc_run_id', CalcRun::query()->where('plan_id', $plan->id)->select('id'))
            ->when($start !== null, fn (Builder $query) => $query->whereHas('transaction', fn (Builder $tx) => $tx
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])))
            ->with('transaction')
            ->get();

        $totals = ['attainment' => 0.0, 'revenue' => 0.0, 'profit' => 0.0];

        foreach ($credits as $credit) {
            $credited = (float) $credit->credited_amount;
            $totals['attainment'] += $credited;

            $metricValue = $credit->transaction?->metricValue($metric) ?? 0.0;
            if ($metricValue != 0.0) {
                $share = $credited / $metricValue;
                $totals['revenue'] += (float) $credit->transaction->amount * $share;
                $totals['profit'] += (float) ($credit->transaction->profit_amount ?? 0) * $share;
            }
        }

        return $totals;
    }

    /**
     * @param  array{plan: array<string, mixed>, tiers: array<int, array<string, mixed>>}  $config
     * @param  array{attainment: float, revenue: float, profit: float}  $totals
     * @return array{attainment: float, attainment_pct: float|null, commission: float, capped: bool, tier: array{number: int, from: float, to: float|null, kind: string, rate_or_amount: float}|null}
     */
    protected function outcome(array $config, array $totals): array
    {
        $result = $this->calculator->calculate($config['plan'], $config['tiers'], $totals);
        $quota = $result['meta']['quota'];

        return [
            'attainment' => round($totals['attainment'], 4),
            'attainment_pct' => $quota ? round($totals['attainment'] / $quota * 100, 2) : null,
            'commission' => round($result['total'], 4),
            'capped' => $result['capped'],
            'tier' => $this->tier($config['tiers'], $totals['attainment']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array{number: int, from: float, to: float|null, kind: string, rate_or_amount: float}|null
     */
    protected function tier(array $tiers, float $attainment): ?array
    {
        $tiers = array_values($tiers);
        $index = $this->tierCalculator->reachedTier($tiers, $attainment);

        if ($index === null) {
            return null;
        }

        $tier = $tiers[$index];

        return [
            'number' => (int) $index + 1,
            'from' => (float) $tier['threshold_from'],
            'to' => isset($tier['threshold_to']) ? (float) $tier['threshold_to'] : null,
            'kind' => (string) ($tier['kind'] ?? 'rate'),
            'rate_or_amount' => (float) $tier['rate_or_amount'],
        ];
    }
}
