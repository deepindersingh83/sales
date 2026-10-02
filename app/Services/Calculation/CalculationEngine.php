<?php

namespace App\Services\Calculation;

use App\Enums\PayoutStatus;
use App\Enums\RewardType;
use App\Models\CalcLog;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Reward;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Orchestrates a calculation run against a plan_version SNAPSHOT (not the live
 * plan), so results are reproducible. Every rule application is written to
 * calc_logs; per-transaction crediting to credits; commission + reward-rule
 * payouts to rewards.
 *
 * Runs inside the workspace context set by the caller (the queued job); the
 * BelongsToWorkspace trait stamps workspace_id on every row it writes.
 */
class CalculationEngine
{
    public function __construct(
        protected TierCalculator $tierCalculator,
        protected AliasCreditingStrategy $strategy,
        protected FormulaEvaluator $formula,
        protected FxConverter $fx,
    ) {}

    /** @var array<int, float> user_id => salary (for salary-based allocations) */
    protected array $salaries = [];

    /** @var array<int, array<string, float>> user_id => reward_type => already-released amount (true-up mode) */
    protected array $alreadyPaid = [];

    /**
     * @return array{credited:int, uncredited:int, reps:int, commission_total:float}
     */
    public function run(CalcRun $run): array
    {
        $snapshot = $run->planVersion->snapshot;
        $metric = $snapshot['plan']['performance_metric'] ?? 'revenue';
        $tiers = $snapshot['tiers'] ?? [];
        $rewardRules = $snapshot['reward_rules'] ?? [];

        $this->strategy->warm();
        $this->fx->forWorkspace($run->workspace_id);
        $planCurrency = $snapshot['plan']['currency'] ?? 'USD';

        // Salaries for salary-based reward allocations.
        $this->salaries = DB::table('workspace_user')
            ->where('workspace_id', $run->workspace_id)
            ->whereNotNull('salary')
            ->pluck('salary', 'user_id')
            ->map(fn ($s) => (float) $s)
            ->all();

        // True-up mode: what each rep was already paid on this plan (released
        // cash rewards from prior runs, per type) — new amounts are netted against it.
        $this->alreadyPaid = [];
        if ($run->mode === 'true_up') {
            $this->alreadyPaid = $this->alreadyPaidByUserAndType($run);
        }

        $credited = 0;
        $uncredited = 0;
        /** @var array<int, array{attainment:float, revenue:float, profit:float}> $acc */
        $acc = [];

        return DB::transaction(function () use ($run, $snapshot, $metric, $tiers, $rewardRules, $planCurrency, &$credited, &$uncredited, &$acc) {
            foreach ($this->transactionsFor($run, $snapshot) as $tx) {
                $allocations = $this->strategy->allocations($tx);

                if (empty($allocations)) {
                    $uncredited++;
                    $this->log($run, 'uncredited', $tx, null, null, null, 'No alias matched this transaction.');

                    continue;
                }

                // Convert the transaction into the plan's currency (1:1 when they
                // match or no rate exists).
                $txDate = optional($tx->transaction_date)->toDateString();
                $rate = $this->fx->rate($tx->currency, $planCurrency, $txDate) ?? 1.0;

                // Exclude-tax basis: net the crediting value down by the plan tax rate.
                $taxRate = $snapshot['plan']['tax_rate_percent'] ?? null;
                $taxFactor = $taxRate !== null ? (1 - ((float) $taxRate) / 100.0) : 1.0;

                $value = $tx->metricValue($metric) * $rate * $taxFactor;
                $convAmount = (float) $tx->amount * $rate * $taxFactor;
                $convProfit = (float) ($tx->profit_amount ?? 0) * $rate * $taxFactor;

                if ($rate != 1.0) {
                    $this->log($run, 'fx_converted', $tx, null, $tx->metricValue($metric), $value,
                        "Converted {$tx->currency} to {$planCurrency} at {$rate}.",
                        ['from' => $tx->currency, 'to' => $planCurrency, 'rate' => $rate]);
                }

                foreach ($allocations as $alloc) {
                    $userId = $alloc['user_id'];
                    $fraction = $alloc['fraction'];
                    $credit = $value * $fraction;

                    Credit::create([
                        'calc_run_id' => $run->id,
                        'transaction_id' => $tx->id,
                        'user_id' => $userId,
                        'credited_amount' => $credit,
                        'currency' => $planCurrency,
                        'status' => 'pending',
                    ]);

                    $this->log($run, 'credit_matched', $tx, $userId, 0.0, $credit,
                        'Credited '.($fraction < 1.0 ? round($fraction * 100, 2).'% of ' : '')."{$metric} value to user #{$userId}.",
                        ['metric' => $metric, 'fraction' => $fraction]);

                    $acc[$userId] ??= ['attainment' => 0.0, 'revenue' => 0.0, 'profit' => 0.0];
                    $acc[$userId]['attainment'] += $credit;
                    $acc[$userId]['revenue'] += $convAmount * $fraction;
                    $acc[$userId]['profit'] += $convProfit * $fraction;
                    $credited++;
                }
            }

            $commissionTotal = 0.0;

            foreach ($acc as $userId => $totals) {
                $commission = $this->computeCommission($run, $userId, $snapshot, $tiers, $totals);

                if ($commission['total'] != 0.0) {
                    Reward::create([
                        'calc_run_id' => $run->id,
                        'user_id' => $userId,
                        'plan_id' => $run->plan_id,
                        'reward_type' => RewardType::Commission->value,
                        'computed_amount' => $commission['total'],
                        'currency' => $snapshot['plan']['currency'] ?? 'USD',
                        'meta' => $commission['meta'],
                        'status' => 'pending',
                    ]);
                    $commissionTotal += $commission['total'];
                }

                $this->applyRewardRules($run, $userId, $totals, $rewardRules, $snapshot);
            }

            if ($run->mode === 'true_up') {
                $commissionTotal += $this->clawBackUncreditedUsers($run, $acc, $snapshot['plan']['currency'] ?? 'USD');
            }

            $this->applyManagerOverrides($run, $snapshot, $acc);

            return [
                'credited' => $credited,
                'uncredited' => $uncredited,
                'reps' => count($acc),
                'commission_total' => round($commissionTotal, 4),
            ];
        });
    }

    /**
     * True-up for users already paid on this plan who have no credited
     * transactions in this run: their gross is zero, so every released
     * commission and reward-rule cash amount is clawed back. Overrides are
     * handled by applyManagerOverrides().
     *
     * @param  array<int, array{attainment:float, revenue:float, profit:float}>  $acc
     * @return float The commission clawed back (negative).
     */
    protected function clawBackUncreditedUsers(CalcRun $run, array $acc, string $currency): float
    {
        $commissionTotal = 0.0;

        foreach (array_keys($this->alreadyPaid) as $userId) {
            if (isset($acc[$userId])) {
                continue;
            }

            $commissionTotal += $this->createTrueUpReward($run, $userId, RewardType::Commission, 0.0, $currency);
            $this->applyRewardRuleTrueUps($run, $userId, [], $currency);
        }

        return $commissionTotal;
    }

    /**
     * A manager earns manager_override_percent of their direct reports'
     * credited attainment (single-level rollup).
     *
     * @param  array<int, array{attainment:float, revenue:float, profit:float}>  $acc
     */
    protected function applyManagerOverrides(CalcRun $run, array $snapshot, array $acc): void
    {
        $isTrueUp = $run->mode === 'true_up';
        $pct = $snapshot['plan']['manager_override_percent'] ?? null;
        if (! $isTrueUp && ($pct === null || (float) $pct == 0.0)) {
            return;
        }
        $pct = (float) $pct;

        // Reporting lines for this workspace: report user_id => manager user_id.
        $managerOf = DB::table('workspace_user')
            ->where('workspace_id', $run->workspace_id)
            ->whereNotNull('manager_id')
            ->pluck('manager_id', 'user_id');

        $teamAttainment = [];
        foreach ($acc as $userId => $totals) {
            $managerId = $managerOf[$userId] ?? null;
            if ($managerId !== null) {
                $teamAttainment[$managerId] = ($teamAttainment[$managerId] ?? 0.0) + $totals['attainment'];
            }
        }

        // True-up: managers paid an override before but with no credited team
        // now earn zero, so their released override is clawed back.
        if ($isTrueUp) {
            foreach ($this->alreadyPaid as $userId => $paidByType) {
                if (isset($paidByType[RewardType::Override->value])) {
                    $teamAttainment[$userId] ??= 0.0;
                }
            }
        }

        foreach ($teamAttainment as $managerId => $teamAtt) {
            $amount = round($teamAtt * $pct / 100.0, 4);

            if ($isTrueUp) {
                $this->createTrueUpReward($run, $managerId, RewardType::Override, $amount, $snapshot['plan']['currency'] ?? 'USD',
                    ['team_attainment' => $teamAtt, 'override_percent' => $pct]);

                continue;
            }

            if ($amount == 0.0) {
                continue;
            }

            Reward::create([
                'calc_run_id' => $run->id,
                'user_id' => $managerId,
                'plan_id' => $run->plan_id,
                'reward_type' => RewardType::Override->value,
                'computed_amount' => $amount,
                'currency' => $snapshot['plan']['currency'] ?? 'USD',
                'meta' => ['team_attainment' => $teamAtt, 'override_percent' => $pct],
                'status' => 'pending',
            ]);

            $this->log($run, 'override_applied', null, $managerId, 0.0, $amount,
                "Manager override {$pct}% on team attainment ".round($teamAtt, 2).'.',
                ['team_attainment' => $teamAtt, 'override_percent' => $pct]);
        }
    }

    /**
     * Compute one rep's commission: a custom formula when the plan defines one,
     * otherwise tier math; then apply the payout cap. Logs each step.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     * @param  array{attainment:float, revenue:float, profit:float}  $totals
     * @return array{total:float, meta:array<string, mixed>}
     */
    protected function computeCommission(CalcRun $run, int $userId, array $snapshot, array $tiers, array $totals): array
    {
        $plan = $snapshot['plan'];
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
            $this->log($run, 'formula_applied', null, $userId, 0.0, $total,
                "Custom formula on attainment {$totals['attainment']}.", $meta);
        } else {
            $result = $this->tierCalculator->commission($tiers, $totals['attainment']);
            $total = $result['total'];
            $meta['method'] = 'tiers';
            $meta['breakdown'] = $result['breakdown'];
            if ($total != 0.0) {
                $this->log($run, 'tier_applied', null, $userId, 0.0, $total,
                    'Tiered commission on attainment '.round($totals['attainment'], 2).'.', $meta);
            }
        }

        // Payout cap.
        $cap = isset($plan['payout_cap']) ? (float) $plan['payout_cap'] : null;
        if ($cap !== null && $total > $cap) {
            $this->log($run, 'cap_applied', null, $userId, $total, $cap,
                "Payout capped at {$cap}.", ['uncapped' => $total, 'cap' => $cap]);
            $meta['uncapped'] = round($total, 4);
            $meta['capped'] = true;
            $total = $cap;
        }

        // True-up: pay only the delta between newly-earned gross and what was
        // already released for this rep on this plan (can be negative — a claw-back).
        if ($run->mode === 'true_up') {
            $paid = $this->alreadyPaid[$userId][RewardType::Commission->value] ?? 0.0;
            $meta['true_up'] = true;
            $meta['gross'] = round($total, 4);
            $meta['already_paid'] = round($paid, 4);
            $total = $total - $paid;
            $this->log($run, 'true_up_applied', null, $userId, $meta['gross'], round($total, 4),
                'True-up delta: gross '.$meta['gross'].' minus already-paid '.$meta['already_paid'].'.', $meta);
        }

        return ['total' => round($total, 4), 'meta' => $meta];
    }

    /**
     * Evaluate the plan's reward rules for one rep, creating rewards + logs.
     *
     * @param  array{attainment:float, revenue:float, profit:float}  $totals
     * @param  array<int, array<string, mixed>>  $rewardRules
     */
    protected function applyRewardRules(CalcRun $run, int $userId, array $totals, array $rewardRules, array $snapshot): void
    {
        $currency = $snapshot['plan']['currency'] ?? 'USD';
        $isTrueUp = $run->mode === 'true_up';

        /** @var array<string, float> $grossByType */
        $grossByType = [];

        foreach ($rewardRules as $rule) {
            $type = RewardType::tryFrom($rule['reward_type'] ?? '');
            if ($type === null) {
                continue;
            }

            $value = isset($rule['value']) ? (float) $rule['value'] : null;
            $amount = null;
            $meta = $rule['meta'] ?? [];

            switch ($type) {
                case RewardType::CashFixed:
                    $amount = $value;
                    break;
                case RewardType::CashPctRevenue:
                    $amount = ($value ?? 0) * $totals['revenue'];
                    break;
                case RewardType::CashPctProfit:
                    $amount = ($value ?? 0) * $totals['profit'];
                    break;
                case RewardType::CashPctSalary:
                    $salary = $this->salaries[$userId] ?? null;
                    if ($salary !== null) {
                        $amount = ($value ?? 0) * $salary;
                    } else {
                        $meta = array_merge($meta ?? [], ['note' => 'No salary on file for this member.']);
                    }
                    break;
                default:
                    // badge / email / announcement / prize — recognition only.
                    break;
            }

            if ($isTrueUp) {
                if ($type->isCash()) {
                    $grossByType[$type->value] = ($grossByType[$type->value] ?? 0.0) + ($amount ?? 0.0);
                }

                continue;
            }

            Reward::create([
                'calc_run_id' => $run->id,
                'user_id' => $userId,
                'plan_id' => $run->plan_id,
                'reward_type' => $type->value,
                'computed_amount' => $amount !== null ? round($amount, 4) : null,
                'currency' => $currency,
                'meta' => $meta ?: null,
                'status' => 'pending',
            ]);

            $this->log($run, 'reward_rule', null, $userId, null, $amount,
                'Applied reward rule: '.$type->label().'.',
                ['reward_type' => $type->value, 'value' => $value]);
        }

        if ($isTrueUp) {
            $this->applyRewardRuleTrueUps($run, $userId, $grossByType, $currency);
        }
    }

    /**
     * True-up for reward-rule cash rewards: pay each type's gross minus what
     * was already released for it, clawing back types released earlier but no
     * longer earned. Commission and overrides are netted separately, and
     * non-cash rewards are never re-issued.
     *
     * @param  array<string, float>  $grossByType
     */
    protected function applyRewardRuleTrueUps(CalcRun $run, int $userId, array $grossByType, string $currency): void
    {
        $types = array_diff(
            array_unique(array_merge(array_keys($grossByType), array_keys($this->alreadyPaid[$userId] ?? []))),
            [RewardType::Commission->value, RewardType::Override->value],
        );

        foreach ($types as $type) {
            $this->createTrueUpReward($run, $userId, RewardType::from($type), $grossByType[$type] ?? 0.0, $currency);
        }
    }

    /**
     * Create a true-up reward for the delta between newly-earned gross and the
     * amount of that type already released to the user on this plan. A zero
     * delta creates nothing; a negative delta is a claw-back.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function createTrueUpReward(CalcRun $run, int $userId, RewardType $type, float $gross, string $currency, array $meta = []): float
    {
        $paid = $this->alreadyPaid[$userId][$type->value] ?? 0.0;
        $delta = round($gross - $paid, 4);

        if ($delta == 0.0) {
            return 0.0;
        }

        $meta = array_merge($meta, [
            'true_up' => true,
            'gross' => round($gross, 4),
            'already_paid' => round($paid, 4),
        ]);

        Reward::create([
            'calc_run_id' => $run->id,
            'user_id' => $userId,
            'plan_id' => $run->plan_id,
            'reward_type' => $type->value,
            'computed_amount' => $delta,
            'currency' => $currency,
            'meta' => $meta,
            'status' => 'pending',
        ]);

        $this->log($run, 'true_up_applied', null, $userId, $meta['gross'], $delta,
            $type->label().' true-up delta: gross '.$meta['gross'].' minus already-paid '.$meta['already_paid'].'.', $meta);

        return $delta;
    }

    /**
     * Released cash rewards from prior runs of this plan, summed per user and
     * type. Manual adjustments are excluded: they are one-off corrections, not
     * amounts a recalculation re-earns.
     *
     * @return array<int, array<string, float>>
     */
    protected function alreadyPaidByUserAndType(CalcRun $run): array
    {
        $cashTypes = collect(RewardType::cases())
            ->filter(fn (RewardType $type): bool => $type->isCash() && $type !== RewardType::Adjustment)
            ->map(fn (RewardType $type): string => $type->value)
            ->values()
            ->all();

        $paid = [];

        Reward::where('plan_id', $run->plan_id)
            ->whereIn('reward_type', $cashTypes)
            ->where('status', PayoutStatus::Released->value)
            ->where('calc_run_id', '!=', $run->id)
            ->get()
            ->each(function (Reward $reward) use (&$paid): void {
                $type = $reward->reward_type->value;
                $paid[$reward->user_id][$type] = ($paid[$reward->user_id][$type] ?? 0.0) + (float) $reward->computed_amount;
            });

        return $paid;
    }

    /**
     * Transactions in scope for this run: within the plan period when the
     * snapshot defines one, otherwise all workspace transactions.
     *
     * @return LazyCollection<int, Transaction>
     */
    protected function transactionsFor(CalcRun $run, array $snapshot)
    {
        $query = Transaction::query()->where('excluded', false);

        // Pay-when-you-get-paid: only credit transactions marked paid.
        if (! empty($snapshot['plan']['pay_when_paid'])) {
            $query->where('is_paid', true);
        }

        $period = $snapshot['plan']['period_type'] ?? null;
        $start = $snapshot['plan']['start_date'] ?? null;
        $end = $snapshot['plan']['end_date'] ?? null;

        // Rolling to-date windows computed at run time.
        if ($period === 'qtd') {
            $start = now()->firstOfQuarter()->toDateString();
            $end = now()->toDateString();
        } elseif ($period === 'ytd') {
            $start = now()->startOfYear()->toDateString();
            $end = now()->toDateString();
        }

        if ($start && $end) {
            $query->whereNotNull('transaction_date')
                ->whereBetween('transaction_date', [$start, $end]);
        }

        // Per-plan transaction filter on a raw_data field (best-effort JSON match).
        $filterField = $snapshot['plan']['filter_field'] ?? null;
        $filterValue = $snapshot['plan']['filter_value'] ?? null;
        if ($filterField && $filterValue !== null && $filterValue !== '') {
            if (in_array($filterField, ['source_system', 'external_id', 'currency'], true)) {
                $query->where($filterField, $filterValue);
            } else {
                $query->where('raw_data->'.$filterField, $filterValue);
            }
        }

        return $query->orderBy('id')->cursor();
    }

    protected function log(CalcRun $run, string $step, ?Transaction $tx, ?int $userId, ?float $before, ?float $after, ?string $description = null, array $context = []): void
    {
        CalcLog::create([
            'calc_run_id' => $run->id,
            'transaction_id' => $tx?->id,
            'user_id' => $userId,
            'step' => $step,
            'description' => $description,
            'amount_before' => $before,
            'amount_after' => $after,
            'context' => $context ?: null,
        ]);
    }
}
