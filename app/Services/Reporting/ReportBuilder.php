<?php

namespace App\Services\Reporting;

use App\Enums\PayoutStatus;
use App\Enums\RewardType;
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

    /**
     * Attainment (released credited amount) + payout per user.
     *
     * @return Collection<int, array{user:string, credited:float, payout:float}>
     */
    public function attainmentByUser(): Collection
    {
        $payouts = Reward::released()->get()->groupBy('user_id')
            ->map(fn ($r) => (float) $r->sum('computed_amount'));

        return Credit::released()->with('user')->get()
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
     * Released payout grouped by reward type.
     *
     * @return Collection<int, array{type:string, total:float}>
     */
    public function payoutByType(): Collection
    {
        return Reward::released()->whereNotNull('computed_amount')->get()
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
        return Reward::released()->whereNotNull('computed_amount')->get()
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
        return Reward::whereIn('status', [PayoutStatus::Pending, PayoutStatus::Reviewed])
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
        return round((float) Reward::whereIn('status', [PayoutStatus::Pending, PayoutStatus::Reviewed])
            ->sum('computed_amount'), 2);
    }

    public function totalReleasedPayout(): float
    {
        return round((float) Reward::released()->sum('computed_amount'), 2);
    }

    public function hasReleasedData(): bool
    {
        return Credit::where('status', PayoutStatus::Released)->exists()
            || Reward::where('status', PayoutStatus::Released)->exists();
    }
}
