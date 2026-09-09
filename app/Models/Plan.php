<?php

namespace App\Models;

use App\Enums\PlanStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'performance_metric',
        'quota',
        'payout_cap',
        'commission_formula',
        'manager_override_percent',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'quota' => 'decimal:4',
            'payout_cap' => 'decimal:4',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(PlanTier::class)->orderBy('sort_order');
    }

    public function rewardRules(): HasMany
    {
        return $this->hasMany(RewardRule::class)->orderBy('id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PlanTerm::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function creditingRules(): HasMany
    {
        return $this->hasMany(CreditingRule::class);
    }

    public function calcRuns(): HasMany
    {
        return $this->hasMany(CalcRun::class);
    }

    /**
     * Plan Admins explicitly assigned to control this plan.
     */
    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_admin_assignments')
            ->withPivotValue('workspace_id', $this->workspace_id)
            ->withTimestamps();
    }

    /**
     * Limited Admins this plan is explicitly hidden from.
     */
    public function hiddenFromUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_visibility_hides')
            ->withPivotValue('workspace_id', $this->workspace_id)
            ->withTimestamps();
    }

    public function latestVersion(): ?PlanVersion
    {
        return $this->versions()->orderByDesc('version_number')->first();
    }

    public function isActive(): bool
    {
        return $this->status === PlanStatus::Active;
    }
}
