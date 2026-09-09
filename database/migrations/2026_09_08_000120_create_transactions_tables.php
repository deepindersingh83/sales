<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions & crediting inputs.
 *
 * Idempotency: transactions dedupe on (workspace_id, source_system, external_id)
 * so re-importing the same file is an upsert, never a duplicate insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('csv');   // csv | api | manual
            $table->json('config')->nullable();        // e.g. saved column mapping
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('source_system')->default('csv');
            $table->json('raw_data')->nullable();      // full original row for auditability
            $table->decimal('amount', 20, 4)->default(0);
            $table->decimal('profit_amount', 20, 4)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->date('transaction_date')->nullable();
            $table->timestamps();

            // Dedup key — enforces idempotent upserts.
            $table->unique(['workspace_id', 'source_system', 'external_id'], 'transactions_dedup_unique');
            $table->index(['workspace_id', 'transaction_date']);
        });

        // The crediting mechanism: an alias is a keyword matched against a field
        // on incoming transactions to determine who (a user, for MVP) gets credited.
        Schema::create('aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('team_id')->nullable();     // reserved for team crediting (Phase 2)
            $table->string('alias_value');             // keyword to match
            $table->string('match_field');             // transaction field / raw_data key to match against
            $table->string('match_type')->default('exact'); // exact | contains
            $table->timestamps();

            $table->index(['workspace_id', 'match_field']);
        });

        // Links a plan to its crediting configuration. Inherits tenancy via plan.
        Schema::create('crediting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('strategy')->default('alias'); // alias | (future strategies)
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crediting_rules');
        Schema::dropIfExists('aliases');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('import_sources');
    }
};
