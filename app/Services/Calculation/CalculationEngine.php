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
                $result = $this->tierCalculator->commission($tiers, $totals['attainment']);

                if ($result['total'] != 0.0) {
                    Reward::create([
                        'calc_run_id' => $run->id,
                        'user_id' => $userId,
                        'plan_id' => $run->plan_id,
                        'reward_type' => RewardType::Commission->value,
                        'computed_amount' => $result['total'],
                        'currency' => $snapshot['plan']['currency'] ?? 'USD',
                        'meta' => ['attainment' => $totals['attainment'], 'breakdown' => $result['breakdown']],
                        'status' => 'pending',
                    ]);
                    $commissionTotal += $result['total'];

                    $this->log($run, 'tier_applied', null, $userId, 0.0, $result['total'],
                        'Tiered commission on attainment '.round($totals['attainment'], 2).'.',
                        ['attainment' => $totals['attainment'], 'breakdown' => $result['breakdown']]);
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
