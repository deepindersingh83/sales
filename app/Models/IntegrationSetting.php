<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace's settings for one integration provider, e.g. its own Xero app
 * client id/secret. Settings are encrypted at rest and never serialised.
 */
class IntegrationSetting extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'provider', 'settings'];

    protected $hidden = ['settings'];

    protected function casts(): array
    {
        return ['settings' => 'encrypted:array'];
    }

    /** The current workspace's settings for a provider (empty when unset). */
    public static function for(string $provider): array
    {
        return static::where('provider', $provider)->first()?->settings ?? [];
    }
}
