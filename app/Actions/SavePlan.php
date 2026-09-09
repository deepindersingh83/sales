<?php

namespace App\Actions;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Persists a plan together with its tiers and reward rules from validated
 * request data. Used by both create and update — on update the tiers and
 * reward rules are replaced wholesale (simplest correct behaviour for the MVP
 * builder; historical calc runs are unaffected because they read a snapshot).
 */
class SavePlan
{
    public function handle(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'period_type' => $data['period_type'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'performance_metric' => $data['performance_metric'],
                'tax_rate_percent' => $data['tax_rate_percent'] ?? null,
                'filter_field' => $data['filter_field'] ?? null,
                'filter_value' => $data['filter_value'] ?? null,
                'pay_when_paid' => (bool) ($data['pay_when_paid'] ?? false),
                'quota' => $data['quota'] ?? null,
                'payout_cap' => $data['payout_cap'] ?? null,
                'commission_formula' => $data['commission_formula'] ?? null,
                'manager_override_percent' => $data['manager_override_percent'] ?? null,
                'currency' => strtoupper($data['currency']),
            ])->save();

            // Replace tiers.
            $plan->tiers()->delete();
            foreach (array_values($data['tiers'] ?? []) as $i => $tier) {
                $plan->tiers()->create([
                    'threshold_from' => $tier['threshold_from'],
                    'threshold_to' => $tier['threshold_to'] ?? null,
                    'kind' => $tier['kind'],
                    'rate_or_amount' => $tier['rate_or_amount'],
                    'is_cumulative' => $tier['is_cumulative'] ?? true,
                    'sort_order' => $i,
                ]);
            }

            // Replace reward rules.
            $plan->rewardRules()->delete();
            foreach ($data['reward_rules'] ?? [] as $rule) {
                $label = $rule['label'] ?? null;
                $plan->rewardRules()->create([
                    'reward_type' => $rule['reward_type'],
                    'value' => $rule['value'] ?? null,
                    'meta' => $label ? ['label' => $label] : null,
                ]);
            }

            return $plan->refresh();
        });
    }
}
