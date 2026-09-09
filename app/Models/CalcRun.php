<?php

namespace App\Models;

use App\Enums\CalcRunStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalcRun extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'plan_version_id',
        'status',
        'is_simulation',
        'mode',
        'error',
        'started_at',
        'completed_at',
        'triggered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CalcRunStatus::class,
            'is_simulation' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CalcLog::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }
}
