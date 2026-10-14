<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A commission tier. Inherits tenancy through its plan (no workspace_id).
 */
class PlanTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'threshold_from',
        'threshold_to',
        'kind',
        'rate_or_amount',
        'is_cumulative',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'threshold_from' => 'decimal:4',
            'threshold_to' => 'decimal:4',
            'rate_or_amount' => 'decimal:6',
            'is_cumulative' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
