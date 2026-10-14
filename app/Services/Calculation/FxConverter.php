<?php

namespace App\Services\Calculation;

use App\Models\FxRate;
use Illuminate\Support\Carbon;

/**
 * Converts amounts between currencies using effective-dated FX rates. A
 * workspace-specific rate wins over a shared/global one; the most recent rate
 * on or before the transaction date is used. Rates are cached per run.
 */
class FxConverter
{
    /** @var array<string, float|null> */
    protected array $cache = [];

    public function __construct(protected ?int $workspaceId = null) {}

    public function forWorkspace(int $workspaceId): static
    {
        $this->workspaceId = $workspaceId;
        $this->cache = [];

        return $this;
    }

    /**
     * Convert an amount from one currency to another. Returns the original
     * amount (1:1) when currencies match or no rate is available; check
     * rateAvailable() if you need to know whether a real rate was applied.
     */
    public function convert(float $amount, string $from, string $to, ?string $date = null): float
    {
        $rate = $this->rate($from, $to, $date);

        return $rate === null ? $amount : $amount * $rate;
    }

    public function rateAvailable(string $from, string $to, ?string $date = null): bool
    {
        return strtoupper($from) === strtoupper($to) || $this->rate($from, $to, $date) !== null;
    }

    public function rate(string $from, string $to, ?string $date = null): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $on = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $key = "{$from}|{$to}|{$on}|{$this->workspaceId}";

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // Direct rate.
        $direct = $this->lookup($from, $to, $on);
        if ($direct !== null) {
            return $this->cache[$key] = $direct;
        }

        // Inverse rate.
        $inverse = $this->lookup($to, $from, $on);
        if ($inverse !== null && $inverse != 0.0) {
            return $this->cache[$key] = 1.0 / $inverse;
        }

        return $this->cache[$key] = null;
    }

    protected function lookup(string $base, string $quote, string $on): ?float
    {
        $rate = FxRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->where('effective_date', '<=', $on)
            ->where(function ($q) {
                $q->whereNull('workspace_id');
                if ($this->workspaceId !== null) {
                    $q->orWhere('workspace_id', $this->workspaceId);
                }
            })
            ->orderByRaw('workspace_id IS NULL')      // workspace-specific first
            ->orderByDesc('effective_date')
            ->value('rate');

        return $rate !== null ? (float) $rate : null;
    }
}
