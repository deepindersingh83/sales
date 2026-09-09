<?php

namespace App\Services\Calculation;

use App\Models\Plan;
use App\Models\PlanVersion;

/**
 * Captures a plan's full configuration as an immutable JSON snapshot so a calc
 * run stays reproducible even after the live plan is edited. This is the audit
 * backbone required by the spec.
 */
class PlanSnapshot
{
    public function capture(Plan $plan, ?int $userId = null): PlanVersion
    {
        // Force a fresh load so the snapshot reflects the plan's current state,
        // even if these relations were loaded (and since mutated) earlier.
        $plan->load(['tiers', 'rewardRules', 'terms']);

        $snapshot = [
            'plan' => [
                'name' => $plan->name,
                'description' => $plan->description,
                'period_type' => $plan->period_type,
                'start_date' => optional($plan->start_date)->toDateString(),
                'end_date' => optional($plan->end_date)->toDateString(),
                'performance_metric' => $plan->performance_metric,
                'tax_rate_percent' => $plan->tax_rate_percent !== null ? (float) $plan->tax_rate_percent : null,
                'filter_field' => $plan->filter_field,
                'filter_value' => $plan->filter_value,
                'pay_when_paid' => (bool) $plan->pay_when_paid,
                'quota' => $plan->quota !== null ? (float) $plan->quota : null,
                'payout_cap' => $plan->payout_cap !== null ? (float) $plan->payout_cap : null,
                'commission_formula' => $plan->commission_formula,
                'manager_override_percent' => $plan->manager_override_percent !== null ? (float) $plan->manager_override_percent : null,
                'currency' => $plan->currency,
            ],
            'tiers' => $plan->tiers->map(fn ($t) => [
                'threshold_from' => (float) $t->threshold_from,
                'threshold_to' => $t->threshold_to !== null ? (float) $t->threshold_to : null,
                'kind' => $t->kind,
                'rate_or_amount' => (float) $t->rate_or_amount,
                'is_cumulative' => (bool) $t->is_cumulative,
                'sort_order' => $t->sort_order,
            ])->values()->all(),
            'reward_rules' => $plan->rewardRules->map(fn ($r) => [
                'reward_type' => $r->reward_type->value,
                'value' => $r->value !== null ? (float) $r->value : null,
                'meta' => $r->meta,
            ])->values()->all(),
            'terms' => $plan->terms->map(fn ($t) => [
                'version' => $t->version,
                'body' => $t->body,
            ])->values()->all(),
        ];

        $nextVersion = (int) $plan->versions()->max('version_number') + 1;

        return $plan->versions()->create([
            'workspace_id' => $plan->workspace_id,
            'version_number' => $nextVersion,
            'snapshot' => $snapshot,
            'created_by_user_id' => $userId,
        ]);
    }
}
