<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\RewardType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'calc_run_id',
        'user_id',
        'plan_id',
        'reward_type',
        'computed_amount',
        'currency',
        'meta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'computed_amount' => 'decimal:4',
            'meta' => 'array',
            'status' => PayoutStatus::class,
        ];
    }

    public function calcRun(): BelongsTo
    {
        return $this->belongsTo(CalcRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Released);
    }
}
