<?php

namespace App\Services\Reporting;

use App\Enums\PayoutStatus;
use App\Models\Credit;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Calculation\FxConverter;
use App\Support\WorkspaceContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Revenue analytics over imported transactions (every source: CSV, API, Xero),
 * independent of whether commissions were calculated. Excluded transactions are
 * ignored, and every amount is converted into the workspace's base currency
 * with the effective-dated FX rates so mixed-currency revenue adds up.
 */
class RevenueAnalytics
{
    /** Breakdown dimensions, key => label. */
    public const DIMENSIONS = [
        'month' => 'Month',
        'quarter' => 'Quarter',
        'year' => 'Year',
        'customer' => 'Customer',
        'product' => 'Product',
        'category' => 'Product category',
        'rep' => 'Sales rep (credited)',
        'source' => 'Data source',
        'currency' => 'Original currency',
        'payment' => 'Payment status (paid / part-paid / unpaid)',
    ];

    public function __construct(
        protected WorkspaceContext $context,
        protected FxConverter $fx,
        protected ReportScope $scope,
    ) {}

    public function baseCurrency(): string
    {
        return $this->context->get()?->base_currency ?: 'USD';
    }

    /**
     * Headline KPIs for the range, with growth against the equally long
     * preceding range when both bounds are given.
     *
     * @return array{revenue:float, paid:float, outstanding:float, profit:float, margin:?float, deals:int, average_deal:float, customers:int, growth:?float, currency:string}
     */
    public function kpis(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = $this->rows($from, $to);
        $revenue = round($rows->sum('revenue'), 2);
        $profitRows = $rows->whereNotNull('profit');
        $profit = round($profitRows->sum('profit'), 2);
        $profitBase = $profitRows->sum('revenue');

        $growth = null;
        if ($from && $to) {
            $days = $from->diffInDays($to) + 1;
            $previous = $this->rows($from->copy()->subDays($days), $from->copy()->subDay())->sum('revenue');
            $growth = $previous > 0 ? round(($revenue - $previous) / $previous * 100, 1) : null;
        }

        return [
            'revenue' => $revenue,
            'paid' => round($rows->sum('paid'), 2),
            'outstanding' => round($rows->sum('outstanding'), 2),
            'profit' => $profit,
            'margin' => $profitBase > 0 ? round($profit / $profitBase * 100, 1) : null,
            'deals' => $rows->count(),
            'average_deal' => $rows->isEmpty() ? 0.0 : round($revenue / $rows->count(), 2),
            'customers' => $rows->pluck('customer')->filter()->unique()->count(),
            'growth' => $growth,
            'currency' => $this->baseCurrency(),
        ];
    }

    /**
     * Revenue grouped by one dimension. Time dimensions sort chronologically;
     * the rest sort by revenue, largest first.
     *
     * @return Collection<int, array{key:string, revenue:float, paid:float, outstanding:float, profit:float, deals:int, share:float}>
     */
    public function breakdown(string $dimension, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        if (! array_key_exists($dimension, self::DIMENSIONS)) {
            throw new \InvalidArgumentException("Unknown revenue dimension [{$dimension}].");
        }

        $rows = $this->rows($from, $to);
        $total = max($rows->sum('revenue'), 0.000001);

        // A credited transaction may be split across reps: attribute revenue by
        // credit share so the rep breakdown still sums to total revenue.
        $grouped = $dimension === 'rep'
            ? $this->repRows($rows)->groupBy('key')
            : $rows->groupBy(fn (array $r) => $r[$dimension] ?? '—');

        $result = $grouped->map(fn (Collection $group, $key) => [
            'key' => (string) ($key === '' ? '—' : $key),
            'revenue' => round($group->sum('revenue'), 2),
            'paid' => round($group->sum('paid'), 2),
            'outstanding' => round($group->sum('outstanding'), 2),
            'profit' => round($group->sum('profit'), 2),
            'deals' => $group->pluck('id')->unique()->count(),
            'share' => round($group->sum('revenue') / $total * 100, 1),
        ])->values();

        return in_array($dimension, ['month', 'quarter', 'year'], true)
            ? $result->sortBy('key')->values()
            : $result->sortByDesc('revenue')->values();
    }

    /**
     * Normalised, base-currency rows for every included transaction in range.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function rows(?Carbon $from, ?Carbon $to): Collection
    {
        $base = $this->baseCurrency();
        $this->fx->forWorkspace((int) $this->context->id());
        $categories = Product::pluck('category', 'sku')->filter();

        return Transaction::query()
            ->where('excluded', false)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to->toDateString()))
            ->get()
            ->map(function (Transaction $t) use ($base, $categories) {
                $date = $t->transaction_date ?? $t->created_at;
                $dateString = $date->toDateString();
                $product = (string) (data_get($t->raw_data, 'product') ?? data_get($t->raw_data, 'sku') ?? '');
                $convert = fn (float $v) => $this->fx->convert($v, $t->currency ?: $base, $base, $dateString);

                $revenue = $convert((float) $t->amount);
                $outstanding = $revenue * $t->outstandingFraction();

                return [
                    'id' => $t->id,
                    'revenue' => $revenue,
                    'paid' => $revenue - $outstanding,
                    'outstanding' => $outstanding,
                    'profit' => $t->profit_amount !== null ? $convert((float) $t->profit_amount) : null,
                    'month' => $date->format('Y-m'),
                    'quarter' => $date->format('Y').'-Q'.$date->quarter,
                    'year' => $date->format('Y'),
                    'customer' => (string) (data_get($t->raw_data, 'customer') ?? ''),
                    'product' => $product,
                    'category' => (string) ($categories[$product] ?? data_get($t->raw_data, 'category') ?? ''),
                    'source' => $t->source_system,
                    'currency' => $t->currency,
                    'payment' => $t->paymentStatus(),
                ];
            });
    }

    /**
     * Expand rows by released credit share per rep; uncredited revenue is
     * reported under "Uncredited".
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function repRows(Collection $rows): Collection
    {
        $credits = $this->scope->credits(Credit::where('status', PayoutStatus::Released))
            ->whereIn('transaction_id', $rows->pluck('id'))
            ->with('user:id,name')
            ->get()
            ->groupBy('transaction_id');

        return $rows->flatMap(function (array $row) use ($credits) {
            $txCredits = $credits->get($row['id']);
            if (! $txCredits) {
                return [array_merge($row, ['key' => 'Uncredited'])];
            }

            $creditedTotal = max($txCredits->sum(fn (Credit $c) => abs((float) $c->credited_amount)), 0.000001);

            return $txCredits->groupBy('user_id')->map(function ($userCredits) use ($row, $creditedTotal) {
                $fraction = $userCredits->sum(fn (Credit $c) => abs((float) $c->credited_amount)) / $creditedTotal;

                return array_merge($row, [
                    'key' => $userCredits->first()->user?->name ?? 'Unknown',
                    'revenue' => $row['revenue'] * $fraction,
                    'paid' => $row['paid'] * $fraction,
                    'outstanding' => $row['outstanding'] * $fraction,
                    'profit' => $row['profit'] !== null ? $row['profit'] * $fraction : null,
                ]);
            })->values();
        });
    }
}
