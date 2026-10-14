<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credit extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'calc_run_id',
        'transaction_id',
        'user_id',
        'team_id',
        'credited_amount',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credited_amount' => 'decimal:4',
            'status' => PayoutStatus::class,
        ];
    }

    public function calcRun(): BelongsTo
    {
        return $this->belongsTo(CalcRun::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Only credits released to reps. */
    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Released);
    }
}
