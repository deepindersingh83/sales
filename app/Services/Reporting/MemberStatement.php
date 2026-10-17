<?php

namespace App\Services\Reporting;

use App\Enums\RewardType;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds one member's commission statement for a period from RELEASED data
 * only — the same numbers the rep sees on their dashboard.
 *
 * Credits fall in the period by their transaction date (falling back to when
 * the credit was written, as ReportBuilder does); rewards by when they were
 * calculated. Amounts are never converted: every total is grouped by the
 * currency it was paid in.
 */
class MemberStatement
{
    /**
     * @param  Collection<int, int>|null  $visiblePlanIds  plans the viewer may see, or null for all
     * @return array{
     *     member: User,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     credits: Collection<int, Credit>,
     *     rewards: Collection<int, Reward>,
     *     summary: Collection<int, array{plan: string, currency: string, credited: float, commission: float, other: float, adjustments: float, total: float}>,
     *     totals: Collection<string, float>
     * }
     */
    public function build(User $member, CarbonImmutable $from, CarbonImmutable $to, ?Collection $visiblePlanIds = null): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $credits = Credit::released()
            ->where('user_id', $member->id)
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereHas('transaction', fn (Builder $tx) => $tx->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]))
                    ->orWhere(fn (Builder $undated) => $undated
                        ->whereHas('transaction', fn (Builder $tx) => $tx->whereNull('transaction_date'))
                        ->whereBetween('created_at', [$from, $to]));
            })
            ->when($visiblePlanIds !== null, fn (Builder $query) => $query->whereIn(
                'calc_run_id',
                CalcRun::query()->whereIn('plan_id', $visiblePlanIds)->select('id'),
            ))
            ->with(['transaction', 'calcRun.plan'])
            ->orderBy('id')
            ->get();

        $rewards = Reward::released()
            ->where('user_id', $member->id)
            ->whereBetween('created_at', [$from, $to])
            ->when($visiblePlanIds !== null, fn (Builder $query) => $query->whereIn('plan_id', $visiblePlanIds))
            ->with('plan')
            ->orderBy('id')
            ->get();

        $summary = $this->summarise($credits, $rewards);

        return [
            'member' => $member,
            'from' => $from,
            'to' => $to,
            'credits' => $credits,
            'rewards' => $rewards,
            'summary' => $summary,
            'totals' => $summary->groupBy('currency')->map(fn (Collection $rows): float => round($rows->sum('total'), 2)),
        ];
    }

    /**
     * The default statement window for a calc run: the run's plan period,
     * stretched to include the day the run was calculated so its rewards fall
     * inside it.
     *
     * @return array{from: string, to: string}
     */
    public static function periodForRun(CalcRun $run): array
    {
        $ranAt = CarbonImmutable::parse($run->created_at ?? now());
        $plan = $run->plan;

        $from = $plan?->start_date ? CarbonImmutable::parse($plan->start_date) : $ranAt->startOfMonth();
        $to = $plan?->end_date ? CarbonImmutable::parse($plan->end_date) : $ranAt->endOfMonth();

        return [
            'from' => $from->min($ranAt)->toDateString(),
            'to' => $to->max($ranAt)->toDateString(),
        ];
    }

    /**
     * One row per plan and currency: credited amount, tier commission, other
     * cash rewards, manual adjustments and the total payout.
     *
     * @param  Collection<int, Credit>  $credits
     * @param  Collection<int, Reward>  $rewards
     * @return Collection<int, array{plan: string, currency: string, credited: float, commission: float, other: float, adjustments: float, total: float}>
     */
    protected function summarise(Collection $credits, Collection $rewards): Collection
    {
        $rows = [];

        $row = function (?Plan $plan, string $currency) use (&$rows): string {
            $key = ($plan?->id ?? 0).'|'.$currency;
            $rows[$key] ??= [
                'plan' => $plan?->name ?? 'Unassigned',
                'currency' => $currency,
                'credited' => 0.0,
                'commission' => 0.0,
                'other' => 0.0,
                'adjustments' => 0.0,
                'total' => 0.0,
            ];

            return $key;
        };

        foreach ($credits as $credit) {
            $key = $row($credit->calcRun?->plan, (string) $credit->currency);
            $rows[$key]['credited'] += (float) $credit->credited_amount;
        }

        foreach ($rewards as $reward) {
            if (! $reward->reward_type->isCash() || $reward->computed_amount === null) {
                continue;
            }

            $key = $row($reward->plan, (string) $reward->currency);
            $amount = (float) $reward->computed_amount;
            $column = match ($reward->reward_type) {
                RewardType::Commission => 'commission',
                RewardType::Adjustment => 'adjustments',
                default => 'other',
            };

            $rows[$key][$column] += $amount;
            $rows[$key]['total'] += $amount;
        }

        return collect($rows)
            ->map(fn (array $values): array => array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $values))
            ->sortBy(['plan', 'currency'])
            ->values();
    }
}
