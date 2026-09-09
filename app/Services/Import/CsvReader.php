<?php

namespace App\Services\Import;

/**
 * Minimal, dependency-free CSV reader. Reads the header row and yields each
 * data row as an associative array keyed by header name.
 */
class CsvReader
{
    /**
     * @return array<int, string>
     */
    public function headers(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        fclose($handle);

        return array_map(fn ($h) => trim((string) $h), $headers);
    }

    /**
     * @return array<int, array<string, string>> first $limit data rows (0 = all)
     */
    public function rows(string $path, int $limit = 0): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            // Skip fully-empty lines.
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $rows[] = $row;

            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Apply a target-field => header mapping to a raw CSV row, producing the
     * normalised shape the TransactionUpserter expects. The full original row
     * is preserved in raw_data for auditability and alias matching.
     *
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>
     */
    public function applyMapping(array $row, array $mapping): array
    {
        $value = fn (?string $header) => $header !== null && $header !== '' ? ($row[$header] ?? null) : null;

        return [
            'external_id' => $value($mapping['external_id'] ?? null),
            'amount' => $value($mapping['amount'] ?? null),
            'profit_amount' => $value($mapping['profit_amount'] ?? null),
            'currency' => $value($mapping['currency'] ?? null),
            'transaction_date' => $value($mapping['transaction_date'] ?? null),
            'raw_data' => $row,
        ];
    }
}
