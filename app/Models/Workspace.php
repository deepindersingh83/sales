<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * The top-level tenant. Every business record belongs to exactly one Workspace.
 * The Workspace itself is NOT tenant-scoped (it is the tenant boundary).
 */
class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_currency',
        'api_token',
        'brand_name',
        'brand_color',
        'logo_url',
        'subscription_tier',
        'subscription_status',
        'trial_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    /** The display name shown in white-labelled UI, falling back to the workspace name. */
    public function displayName(): string
    {
        return $this->brand_name ?: $this->name;
    }

    /** The current subscription tier key, defaulting to the configured default. */
    public function tier(): string
    {
        return $this->subscription_tier ?: config('billing.default_tier', 'free');
    }

    /** The tier's configuration array. */
    public function tierConfig(): array
    {
        $tiers = config('billing.tiers');

        return $tiers[$this->tier()] ?? $tiers[config('billing.default_tier', 'free')];
    }

    /** Is the workspace currently within a free trial? */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** A paid tier (anything other than free) counts as a subscription. */
    public function onPaidTier(): bool
    {
        return $this->tier() !== 'free';
    }

    /**
     * Hard cap on active payees for the current tier, or null for unlimited.
     * A trial lifts the free-tier cap so prospects can evaluate at full size.
     */
    public function payeeLimit(): ?int
    {
        if ($this->onPaidTier() || $this->onTrial()) {
            return null;
        }

        return $this->tierConfig()['max_payees'] ?? null;
    }

    /**
     * Generate (or regenerate) this workspace's API token and return it.
     */
    public function regenerateApiToken(): string
    {
        $token = 'wsk_'.Str::random(48);
        $this->forceFill(['api_token' => $token])->save();

        return $token;
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = static::uniqueSlug($workspace->name);
            }
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Members of the workspace, with their per-workspace role on the pivot.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role', 'manager_id', 'salary'])
            ->withTimestamps();
    }
}
