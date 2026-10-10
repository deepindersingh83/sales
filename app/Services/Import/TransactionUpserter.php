<?php

namespace App\Services\Import;

use App\Models\ImportSource;
use App\Models\Transaction;
use App\Support\WorkspaceContext;
use Illuminate\Support\Carbon;

/**
 * Persists normalised transaction rows idempotently. Re-importing the same
 * data updates the existing rows (matched on workspace + source_system +
 * external_id) instead of inserting duplicates.
 */
class TransactionUpserter
{
    public function __construct(protected WorkspaceContext $context) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return array{created:int, updated:int, skipped:int}
     */
    public function upsert(iterable $rows, string $sourceSystem, ?ImportSource $source = null): array
    {
        $workspaceId = $this->context->id();
        $baseCurrency = $this->context->get()?->base_currency ?: 'USD';
        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            $externalId = trim((string) ($row['external_id'] ?? ''));

            // A row with no stable external id cannot be deduplicated — skip it
            // rather than risk duplicate inserts on re-import.
            if ($externalId === '') {
                $skipped++;

                continue;
            }

            // A date that is present but unreadable would be stored as blank and
            // silently fall outside every plan period — skip the row instead.
            $date = $this->toDate($row['transaction_date'] ?? null);
            if ($date === false) {
                $skipped++;

                continue;
            }

            $existing = Transaction::where('source_system', $sourceSystem)
                ->where('external_id', $externalId)
                ->first();

            $attributes = [
                'workspace_id' => $workspaceId,
                'import_source_id' => $source?->id,
                'external_id' => $externalId,
                'source_system' => $sourceSystem,
                'raw_data' => $row['raw_data'] ?? [],
                'amount' => $this->toDecimal($row['amount'] ?? 0),
                'profit_amount' => isset($row['profit_amount']) && $row['profit_amount'] !== ''
                    ? $this->toDecimal($row['profit_amount'])
                    : null,
                'currency' => strtoupper(trim((string) ($row['currency'] ?? ''))) ?: $baseCurrency,
                'transaction_date' => $date,
            ];

            // Sources that know payment state (e.g. Xero) drive pay-when-paid.
            if (array_key_exists('is_paid', $row)) {
                $attributes['is_paid'] = (bool) $row['is_paid'];
            }

            // A source can exclude a row (e.g. a voided invoice) but never
            // re-include one — that would undo an admin's manual exclusion.
            if (! empty($row['excluded'])) {
                $attributes['excluded'] = true;
            }

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                Transaction::create($attributes);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    /**
     * Parse an amount as spreadsheets and accounting exports write it:
     * "$1,234.50", "1.234,50" (European), "(100.00)" or "100-" (negative).
     * When both "." and "," appear, the last one is the decimal separator; a
     * lone "," followed by exactly three digits is a thousands separator.
     */
    protected function toDecimal(mixed $value): float
    {
        if (! is_string($value)) {
            return (float) $value;
        }

        $value = trim($value);
        $negative = (bool) preg_match('/^\(.*\)$|-\s*$|^-|^[^0-9]*-/', $value);
        $number = preg_replace('/[^0-9.,]/', '', $value) ?? '';

        $lastDot = strrpos($number, '.');
        $lastComma = strrpos($number, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
        } elseif ($lastComma !== false) {
            $decimal = preg_match('/^\d{1,3}(,\d{3})+$/', $number) ? null : ',';
        } elseif ($lastDot !== false && substr_count($number, '.') > 1) {
            $decimal = null; // "1.234.567" — dots are thousands separators
        } else {
            $decimal = '.';
        }

        $thousands = $decimal === ',' ? '.' : ',';
        $number = str_replace($decimal === null ? ['.', ','] : $thousands, '', $number);
        if ($decimal === ',') {
            $number = str_replace(',', '.', $number);
        }

        return ($negative ? -1 : 1) * (float) $number;
    }

    /**
     * Parse a date with explicit formats. ISO (Y-m-d) always works; for
     * slash/dot dates the day/month order comes from config
     * (app.import_date_order, "dmy" by default) unless one part is > 12 and so
     * unambiguous. Returns null for blank input and false when unreadable.
     */
    protected function toDate(mixed $value): string|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ].*)?$/', $value, $m)) {
            return $this->validDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})(?:[T ].*)?$#', $value, $m)) {
            [$first, $second, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $year = $year < 100 ? 2000 + $year : $year;
            $dayFirst = $first > 12 || ($second <= 12 && config('app.import_date_order', 'dmy') === 'dmy');

            return $dayFirst
                ? $this->validDate($year, $second, $first)
                : $this->validDate($year, $first, $second);
        }

        // Spelled-out months ("15 Aug 2026", "August 15, 2026").
        if (preg_match('/[a-z]{3}/i', $value)) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function validDate(int $year, int $month, int $day): string|false
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : false;
    }
}
