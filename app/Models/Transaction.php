<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToWorkspace, HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'amount' => 'decimal:4',
            'profit_amount' => 'decimal:4',
            'transaction_date' => 'date',
        ];
    }

    public function importSource(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class);
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
