<?php

namespace App\Services\Import;

/**
 * Best-effort auto-detection of which CSV column maps to which transaction
 * field. Returns a mapping the admin can override in the UI before importing.
 */
class ColumnMapper
{
    /**
     * Target fields and the header keywords that hint at them, in priority order.
     *
     * @var array<string, array<int, string>>
     */
    protected array $hints = [
        'external_id' => ['external_id', 'externalid', 'transaction_id', 'order_id', 'invoice_id', 'invoice', 'deal_id', 'id'],
        'amount' => ['amount', 'revenue', 'total', 'sales_amount', 'value', 'sale', 'net'],
        'profit_amount' => ['profit', 'margin', 'gross_profit', 'gp'],
        'currency' => ['currency', 'ccy', 'curr'],
        'transaction_date' => ['transaction_date', 'close_date', 'closed_at', 'date', 'invoice_date', 'order_date'],
    ];

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string|null> target field => chosen header (or null)
     */
    public function autoDetect(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $normalized[$header] = $this->normalize($header);
        }

        $mapping = [];
        $used = [];

        foreach ($this->hints as $field => $keywords) {
            $mapping[$field] = null;

            foreach ($keywords as $keyword) {
                foreach ($normalized as $original => $norm) {
                    if (in_array($original, $used, true)) {
                        continue;
                    }
                    if ($norm === $keyword || str_contains($norm, $keyword)) {
                        $mapping[$field] = $original;
                        $used[] = $original;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Candidate fields a transaction can be credited on (used for alias matching):
     * every header, since aliases match against raw_data keys.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    public function creditFieldCandidates(array $headers): array
    {
        return $headers;
    }

    protected function normalize(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))) ?? '';
    }
}
