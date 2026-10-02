<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store workspace API tokens as SHA-256 hashes instead of plaintext. Existing
 * tokens are hashed in place, so integrations keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workspaces')
            ->where('api_token', 'like', 'wsk_%')
            ->orderBy('id')
            ->each(function (object $workspace) {
                DB::table('workspaces')
                    ->where('id', $workspace->id)
                    ->update(['api_token' => hash('sha256', $workspace->api_token)]);
            });
    }

    public function down(): void
    {
        // Hashes cannot be reversed; admins regenerate tokens after a rollback.
        DB::table('workspaces')->whereNotNull('api_token')->update(['api_token' => null]);
    }
};
