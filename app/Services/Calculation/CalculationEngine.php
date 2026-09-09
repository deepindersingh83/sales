<?php

namespace App\Services\Calculation;

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
    ) {}

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

        $credited = 0;
        $uncredited = 0;
        /** @var array<int, array{attainment:float, revenue:float, profit:float}> $acc */
        $acc = [];

        return DB::transaction(function () use ($run, $snapshot, $metric, $tiers, $rewardRules, &$credited, &$uncredited, &$acc) {
            foreach ($this->transactionsFor($run, $snapshot) as $tx) {
                $userId = $this->strategy->creditUserId($tx);

                if ($userId === null) {
                    $uncredited++;
                    $this->log($run, 'uncredited', $tx, null, null, null, 'No alias matched this transaction.');

                    continue;
                }

                $value = $tx->metricValue($metric);

                Credit::create([
                    'calc_run_id' => $run->id,
                    'transaction_id' => $tx->id,
                    'user_id' => $userId,
                    'credited_amount' => $value,
                    'currency' => $tx->currency,
                    'status' => 'pending',
                ]);

                $this->log($run, 'credit_matched', $tx, $userId, 0.0, $value,
                    "Credited {$metric} value to user #{$userId}.",
                    ['metric' => $metric]);

                $acc[$userId] ??= ['attainment' => 0.0, 'revenue' => 0.0, 'profit' => 0.0];
                $acc[$userId]['attainment'] += $value;
                $acc[$userId]['revenue'] += (float) $tx->amount;
                $acc[$userId]['profit'] += (float) ($tx->profit_amount ?? 0);
                $credited++;
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

            return [
                'credited' => $credited,
                'uncredited' => $uncredited,
                'reps' => count($acc),
                'commission_total' => round($commissionTotal, 4),
            ];
        });
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
                    // No salary data model in the MVP — record a note, no amount.
                    $meta = array_merge($meta ?? [], ['note' => 'No salary on file (MVP).']);
                    break;
                default:
                    // badge / email / announcement / prize — recognition only.
                    break;
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
    }

    /**
     * Transactions in scope for this run: within the plan period when the
     * snapshot defines one, otherwise all workspace transactions.
     *
     * @return LazyCollection<int, Transaction>
     */
    protected function transactionsFor(CalcRun $run, array $snapshot)
    {
        $query = Transaction::query();

        $start = $snapshot['plan']['start_date'] ?? null;
        $end = $snapshot['plan']['end_date'] ?? null;

        if ($start && $end) {
            $query->whereNotNull('transaction_date')
                ->whereBetween('transaction_date', [$start, $end]);
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
