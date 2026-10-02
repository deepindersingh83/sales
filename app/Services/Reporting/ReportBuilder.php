<?php

namespace App\Services\Reporting;

use App\Enums\PayoutStatus;
use App\Enums\RewardType;
use App\Models\Credit;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregations for the built-in MVP reports. All figures are on RELEASED data
 * (final payouts/credits), matching what reps and finance actually see.
 */
class ReportBuilder
{
    /** Built-in crediting dimensions (the rest come from transaction raw_data). */
    public const CREDITING_DIMENSIONS = [
        'rep' => 'Sales rep',
        'plan' => 'Plan',
        'month' => 'Month',
        'source' => 'Data source',
    ];

    /** Quota-attainment bands for the distribution report, [label, from%, to%). */
    public const ATTAINMENT_BANDS = [
        ['< 50%', 0, 50],
        ['50–75%', 50, 75],
        ['75–100%', 75, 100],
        ['100–125%', 100, 125],
        ['≥ 125%', 125, PHP_INT_MAX],
    ];

    public function __construct(
        protected WorkspaceContext $context,
        protected ReportScope $scope,
    ) {}

    /** Released rewards on plans the viewer may see. */
    protected function releasedRewards(): Builder
    {
        return $this->scope->rewards(Reward::released());
    }

    /** Released credits on plans the viewer may see. */
    protected function releasedCredits(): Builder
    {
        return $this->scope->credits(Credit::released());
    }

    /** Earned-but-unreleased rewards (liability) on plans the viewer may see. */
    protected function accruedRewards(): Builder
    {
        return $this->scope->rewards(Reward::whereIn('status', [PayoutStatus::Pending, PayoutStatus::Reviewed]));
    }

    /**
     * Total released payout per user.
     *
     * @return Collection<int, array{user:string, total:float, currency:string}>
     */
    public function payoutByUser(): Collection
    {
        return $this->releasedRewards()
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'user' => $rows->first()->user?->name ?? 'Unknown',
                'total' => round((float) $rows->sum('computed_amount'), 2),
                'currency' => $rows->first()->currency,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Total released payout per plan.
     *
     * @return Collection<int, array{plan:string, total:float, currency:string}>
     */
    public function payoutByPlan(): Collection
    {
        return $this->releasedRewards()
            ->with('plan')
            ->get()
            ->groupBy('plan_id')
            ->map(fn ($rows) => [
                'plan' => $rows->first()->plan?->name ?? 'Unknown',
                'total' => round((float) $rows->sum('computed_amount'), 2),
                'currency' => $rows->first()->currency,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Total released credited amount grouped by a raw_data field (e.g. product,
     * customer). Grouped in PHP because raw_data is JSON.
     *
     * @return Collection<int, array{key:string, total:float, count:int}>
     */
    public function creditingByField(string $field): Collection
    {
        return $this->releasedCredits()
            ->with('transaction')
            ->get()
            ->groupBy(fn (Credit $c) => (string) data_get($c->transaction?->raw_data, $field, '—'))
            ->map(fn ($rows, $key) => [
                'key' => $key === '' ? '—' : $key,
                'total' => round((float) $rows->sum('credited_amount'), 2),
                'count' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Candidate raw_data keys across released credits' transactions, for the
     * crediting report's field selector.
     *
     * @return array<int, string>
     */
    public function creditingFieldOptions(): array
    {
        $keys = $this->releasedCredits()
            ->with('transaction')
            ->get()
            ->flatMap(fn (Credit $c) => array_keys($c->transaction?->raw_data ?? []))
            ->unique()
            ->values()
            ->all();

        $keys = array_values(array_diff($keys ?: ['product'], array_keys(self::CREDITING_DIMENSIONS)));

        return array_merge(array_keys(self::CREDITING_DIMENSIONS), $keys);
    }

    /**
     * Released credited amount grouped by a built-in dimension (rep, plan,
     * month, source) or, failing that, by a transaction raw_data field.
     *
     * @return Collection<int, array{key:string, total:float, count:int}>
     */
    public function creditingBy(string $dimension): Collection
    {
        if (! array_key_exists($dimension, self::CREDITING_DIMENSIONS)) {
            return $this->creditingByField($dimension);
        }

        $key = match ($dimension) {
            'rep' => fn (Credit $c) => $c->user?->name ?? 'Unknown',
            'plan' => fn (Credit $c) => $c->calcRun?->plan?->name ?? 'Unknown',
            'month' => fn (Credit $c) => ($c->transaction?->transaction_date ?? $c->created_at)->format('Y-m'),
            'source' => fn (Credit $c) => $c->transaction?->source_system ?? '—',
        };

        $rows = $this->releasedCredits()
            ->with(['user:id,name', 'calcRun.plan:id,name', 'transaction'])
            ->get()
            ->groupBy($key)
            ->map(fn ($rows, $k) => [
                'key' => (string) $k,
                'total' => round((float) $rows->sum('credited_amount'), 2),
                'count' => $rows->count(),
            ]);

        return $dimension === 'month'
            ? $rows->sortBy('key')->values()
            : $rows->sortByDesc('total')->values();
    }

    /**
     * Included transactions that no calculation run has credited to anyone —
     * usually a missing alias. The crediting "exceptions" list.
     *
     * @return Collection<int, Transaction>
     */
    public function uncreditedTransactions(): Collection
    {
        return Transaction::query()
            ->where('excluded', false)
            ->whereNotIn('id', Credit::query()->select('transaction_id'))
            ->orderByDesc('transaction_date')
            ->get();
    }

    /**
     * Attainment (released credited amount) + payout per user.
     *
     * @return Collection<int, array{user:string, credited:float, payout:float}>
     */
    public function attainmentByUser(): Collection
    {
        $payouts = $this->releasedRewards()->get()->groupBy('user_id')
            ->map(fn ($r) => (float) $r->sum('computed_amount'));

        return $this->releasedCredits()->with('user')->get()
            ->groupBy('user_id')
            ->map(fn ($rows, $userId) => [
                'user' => $rows->first()->user?->name ?? 'Unknown',
                'credited' => round((float) $rows->sum('credited_amount'), 2),
                'payout' => round((float) ($payouts[$userId] ?? 0), 2),
            ])
            ->sortByDesc('credited')
            ->values();
    }

    /**
     * Quota attainment per rep per plan: released credited amount from that
     * plan's runs against the plan quota. Plans without a quota are skipped.
     *
     * @return Collection<int, array{user_id:int, user:string, plan:string, credited:float, quota:float, attainment:float}>
     */
    public function quotaAttainment(): Collection
    {
        $plans = Plan::whereNotNull('quota')->where('quota', '>', 0)->get()->keyBy('id');

        return $this->releasedCredits()
            ->with(['user:id,name', 'calcRun:id,plan_id'])
            ->get()
            ->filter(fn (Credit $c) => $plans->has($c->calcRun?->plan_id))
            ->groupBy(fn (Credit $c) => $c->user_id.'|'.$c->calcRun->plan_id)
            ->map(function ($rows) use ($plans) {
                $plan = $plans[$rows->first()->calcRun->plan_id];
                $credited = (float) $rows->sum('credited_amount');

                return [
                    'user_id' => (int) $rows->first()->user_id,
                    'user' => $rows->first()->user?->name ?? 'Unknown',
                    'plan' => $plan->name,
                    'credited' => round($credited, 2),
                    'quota' => round((float) $plan->quota, 2),
                    'attainment' => round($credited / (float) $plan->quota * 100, 1),
                ];
            })
            ->sortByDesc('attainment')
            ->values();
    }

    /**
     * Attainment rolled up per plan: participants, total credited, payout and
     * average quota attainment.
     *
     * @return Collection<int, array{plan:string, reps:int, credited:float, payout:float, avg_attainment:?float}>
     */
    public function attainmentByPlan(): Collection
    {
        $quota = $this->quotaAttainment()->groupBy('plan');
        $payouts = $this->releasedRewards()->get()->groupBy('plan_id')
            ->map(fn ($r) => (float) $r->sum('computed_amount'));

        return $this->releasedCredits()
            ->with('calcRun.plan:id,name')
            ->get()
            ->groupBy(fn (Credit $c) => $c->calcRun?->plan_id)
            ->map(function ($rows, $planId) use ($quota, $payouts) {
                $name = $rows->first()->calcRun?->plan?->name ?? 'Unknown';

                return [
                    'plan' => $name,
                    'reps' => $rows->pluck('user_id')->unique()->count(),
                    'credited' => round((float) $rows->sum('credited_amount'), 2),
                    'payout' => round((float) ($payouts[$planId] ?? 0), 2),
                    'avg_attainment' => isset($quota[$name]) ? round($quota[$name]->avg('attainment'), 1) : null,
                ];
            })
            ->sortByDesc('credited')
            ->values();
    }

    /**
     * Team attainment: each manager's direct reports' released credits and
     * payout (single-level reporting lines, as configured on members).
     *
     * @return Collection<int, array{manager:string, reps:int, credited:float, payout:float}>
     */
    public function attainmentByManager(): Collection
    {
        $workspace = $this->context->get();
        if (! $workspace) {
            return collect();
        }

        $members = $workspace->users()->get();
        $names = $members->pluck('name', 'id');
        $managerOf = $members->mapWithKeys(fn ($u) => [$u->id => $u->pivot->manager_id]);

        $credited = $this->releasedCredits()->get()->groupBy('user_id')->map(fn ($r) => (float) $r->sum('credited_amount'));
        $paid = $this->releasedRewards()->get()->groupBy('user_id')->map(fn ($r) => (float) $r->sum('computed_amount'));

        return $managerOf->filter()
            ->groupBy(fn ($managerId) => $managerId, preserveKeys: true)
            ->map(fn ($reports, $managerId) => [
                'manager' => $names[$managerId] ?? 'Unknown',
                'reps' => $reports->count(),
                'credited' => round($reports->keys()->sum(fn ($id) => $credited[$id] ?? 0), 2),
                'payout' => round($reports->keys()->sum(fn ($id) => $paid[$id] ?? 0), 2),
            ])
            ->sortByDesc('credited')
            ->values();
    }

    /**
     * How many rep/plan pairs fall in each quota-attainment band.
     *
     * @return Collection<int, array{band:string, count:int}>
     */
    public function attainmentDistribution(): Collection
    {
        $rows = $this->quotaAttainment();

        return collect(self::ATTAINMENT_BANDS)->map(fn (array $band) => [
            'band' => $band[0],
            'count' => $rows->filter(fn ($r) => $r['attainment'] >= $band[1] && $r['attainment'] < $band[2])->count(),
        ]);
    }

    /**
     * Released payout grouped by reward type.
     *
     * @return Collection<int, array{type:string, total:float}>
     */
    public function payoutByType(): Collection
    {
        return $this->releasedRewards()->whereNotNull('computed_amount')->get()
            ->groupBy(fn (Reward $r) => $r->reward_type->value)
            ->map(fn ($rows, $type) => [
                'type' => RewardType::from($type)->label(),
                'total' => round((float) $rows->sum('computed_amount'), 2),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Released payout grouped by calendar month.
     *
     * @return Collection<int, array{month:string, total:float}>
     */
    public function payoutByMonth(): Collection
    {
        return $this->releasedRewards()->whereNotNull('computed_amount')->get()
            ->groupBy(fn (Reward $r) => $r->created_at->format('Y-m'))
            ->map(fn ($rows, $month) => [
                'month' => $month,
                'total' => round((float) $rows->sum('computed_amount'), 2),
            ])
            ->sortBy('month')
            ->values();
    }

    /**
     * Accrued commission liability — rewards earned but not yet released (paid),
     * grouped by user. This is what finance owes but has not disbursed.
     *
     * @return Collection<int, array{user:string, liability:float}>
     */
    public function liabilityByUser(): Collection
    {
        return $this->accruedRewards()
            ->whereNotNull('computed_amount')
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'user' => $rows->first()->user?->name ?? 'Unknown',
                'liability' => round((float) $rows->sum('computed_amount'), 2),
            ])
            ->sortByDesc('liability')
            ->values();
    }

    public function totalLiability(): float
    {
        return round((float) $this->accruedRewards()
            ->sum('computed_amount'), 2);
    }

    public function totalReleasedPayout(): float
    {
        return round((float) $this->releasedRewards()->sum('computed_amount'), 2);
    }

    public function hasReleasedData(): bool
    {
        return $this->releasedCredits()->exists() || $this->releasedRewards()->exists();
    }
}
