<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToWorkspace, HasFactory, HasTags;

    protected $fillable = [
        'workspace_id',
        'import_source_id',
        'external_id',
        'source_system',
        'raw_data',
        'amount',
        'profit_amount',
        'currency',
        'transaction_date',
        'excluded',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'amount' => 'decimal:4',
            'profit_amount' => 'decimal:4',
            'transaction_date' => 'date',
            'excluded' => 'boolean',
            'is_paid' => 'boolean',
        ];
    }

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
    }

    /** Human reference: the invoice number when the source has one (Xero), else the external id. */
    public function reference(): string
    {
        return (string) (data_get($this->raw_data, 'invoice_number') ?: $this->external_id);
    }

    public function customer(): ?string
    {
        $customer = data_get($this->raw_data, 'customer');

        return filled($customer) ? (string) $customer : null;
    }

    /**
     * Share of this transaction still unpaid, 0..1. Sources that report an
     * amount due (Xero: AmountDue of Total) give partial payments; otherwise
     * it follows the paid flag.
     */
    public function outstandingFraction(): float
    {
        $due = data_get($this->raw_data, 'amount_due');
        $total = data_get($this->raw_data, 'total');

        if (is_numeric($due) && is_numeric($total) && (float) $total > 0) {
            return max(0.0, min(1.0, (float) $due / (float) $total));
        }

        return $this->is_paid ? 0.0 : 1.0;
    }

    /** Paid, Part-paid or Unpaid. */
    public function paymentStatus(): string
    {
        $fraction = $this->outstandingFraction();

        return match (true) {
            $fraction <= 0.0 => 'Paid',
            $fraction >= 1.0 => 'Unpaid',
            default => 'Part-paid',
        };
    }

    /**
     * Value of this transaction for a given performance metric (revenue/profit).
     */
    public function metricValue(string $metric): float
    {
        return match ($metric) {
            'profit' => (float) ($this->profit_amount ?? 0),
            default => (float) $this->amount,
        };
    }
}
