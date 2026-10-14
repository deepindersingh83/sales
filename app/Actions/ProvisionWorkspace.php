<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Creates a workspace and attaches the given user as its Full Admin.
 * Reused by registration, seeders, and tests so the "first member owns the
 * workspace" invariant lives in one place.
 */
class ProvisionWorkspace
{
    public function handle(User $user, string $name, string $baseCurrency = 'USD'): Workspace
    {
        return DB::transaction(function () use ($user, $name, $baseCurrency) {
            $workspace = Workspace::create([
                'name' => $name,
                'base_currency' => $baseCurrency,
            ]);

            $workspace->users()->attach($user->id, ['role' => Role::FullAdmin->value]);

            return $workspace;
        });
    }
}
