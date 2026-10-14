<?php

namespace App\Services\Import;

/**
 * Minimal, dependency-free CSV reader. Reads the header row and yields each
 * data row as an associative array keyed by header name.
 *
 * Copes with what spreadsheets actually export: a UTF-8 byte-order mark,
 * semicolon- or tab-delimited files (European Excel), and legacy
 * Windows-1252/Latin-1 text, which is converted to UTF-8.
 */
class CsvReader
{
    /**
     * @return array<int, string>
     */
    public function headers(string $path): array
    {
        [$handle, $delimiter] = $this->open($path);
        if ($handle === null) {
            return [];
        }

        $headers = fgetcsv($handle, null, $delimiter, '"', '') ?: [];
        fclose($handle);

        return $this->headerNames($headers);
    }

    /**
     * @return array<int, array<string, string>> first $limit data rows (0 = all)
     */
    public function rows(string $path, int $limit = 0): array
    {
        [$handle, $delimiter] = $this->open($path);
        if ($handle === null) {
            return [];
        }

        $headers = fgetcsv($handle, null, $delimiter, '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }
        $headers = $this->headerNames($headers);

        $rows = [];
        while (($data = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            // Skip fully-empty lines.
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($data[$i]) ? $this->cell($data[$i]) : '';
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
     * Open the file and sniff its delimiter from the header line.
     *
     * @return array{0: resource|null, 1: string}
     */
    protected function open(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [null, ','];
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        return [$handle, $counts[$delimiter] > 0 ? $delimiter : ','];
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    protected function headerNames(array $headers): array
    {
        $names = array_map(fn ($h) => $this->cell($h), $headers);

        if (isset($names[0])) {
            $names[0] = preg_replace('/^\x{FEFF}/u', '', $names[0]) ?? $names[0];
        }

        return $names;
    }

    /** Trim a cell and make sure it is valid UTF-8 (legacy exports are Windows-1252). */
    protected function cell(?string $value): string
    {
        $value = (string) $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
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
