<?php

namespace App\Models;

use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reward rule on a plan. Inherits tenancy through its plan.
 */
class RewardRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'reward_type',
        'value',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'value' => 'decimal:6',
            'meta' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
