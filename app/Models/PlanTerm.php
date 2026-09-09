<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned legal terms for a plan. Inherits tenancy through its plan.
 */
class PlanTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'version',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
