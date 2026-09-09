<?php

namespace App\Services\Reporting;

use App\Enums\PayoutStatus;
use App\Models\Credit;
use App\Models\Reward;
use Illuminate\Support\Collection;

/**
 * Aggregations for the built-in MVP reports. All figures are on RELEASED data
 * (final payouts/credits), matching what reps and finance actually see.
 */
class ReportBuilder
{
    /**
     * Total released payout per user.
     *
     * @return Collection<int, array{user:string, total:float, currency:string}>
     */
    public function payoutByUser(): Collection
    {
        return Reward::released()
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
        return Reward::released()
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
        return Credit::released()
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
        $keys = Credit::released()
            ->with('transaction')
            ->get()
            ->flatMap(fn (Credit $c) => array_keys($c->transaction?->raw_data ?? []))
            ->unique()
            ->values()
            ->all();

        return $keys ?: ['product'];
    }

    public function hasReleasedData(): bool
    {
        return Credit::where('status', PayoutStatus::Released)->exists()
            || Reward::where('status', PayoutStatus::Released)->exists();
    }
}
