<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculation engine tables. Auditability is first-class: calc_logs records
 * every rule application against every transaction (before/after amounts) so
 * any credit or reward can be fully traced and any dispute resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calc_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued'); // queued | running | completed | failed
            $table->boolean('is_simulation')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'plan_id', 'status']);
        });

        Schema::create('calc_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calc_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('step');                    // e.g. credit_matched, tier_applied
            $table->text('description')->nullable();
            $table->decimal('amount_before', 20, 6)->nullable();
            $table->decimal('amount_after', 20, 6)->nullable();
            $table->json('context')->nullable();       // tier id, alias id, rate, etc.
            $table->timestamps();

            $table->index(['calc_run_id', 'transaction_id']);
        });

        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calc_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('team_id')->nullable();
            $table->decimal('credited_amount', 20, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending | reviewed | released
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'status']);
            $table->index(['calc_run_id', 'status']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calc_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type');
            $table->decimal('computed_amount', 20, 4)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->json('meta')->nullable();          // badge label, prize, announcement copy
            $table->string('status')->default('pending'); // pending | reviewed | released
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'status']);
            $table->index(['calc_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('calc_logs');
        Schema::dropIfExists('calc_runs');
    }
};
