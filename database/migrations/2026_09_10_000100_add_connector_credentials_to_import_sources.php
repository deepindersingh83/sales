<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P25 — live connectors (Xero first). An import source can now be an API
 * connection instead of a stored CSV: its OAuth tokens live in `credentials`
 * (encrypted at rest by the model cast), and the outcome of the last sync is
 * kept in `last_error` so admins can see why a feed stopped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('config');
            $table->text('last_error')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn(['credentials', 'last_error']);
        });
    }
};
