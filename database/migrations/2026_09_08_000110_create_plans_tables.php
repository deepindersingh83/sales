<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan design tables.
 *
 * Tenancy: `plans`, `plan_versions`, and `enrollments` carry workspace_id (they
 * are queried directly). `plan_tiers`, `reward_rules`, and `plan_terms` inherit
 * tenancy through their parent plan and intentionally have no workspace_id,
 * per the spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('period_type');        // monthly | quarterly | annual | custom
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('draft');   // draft | active | archived
            $table->string('performance_metric')->default('revenue'); // revenue | profit | custom
            $table->char('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        // Immutable snapshot of a plan's full config at calculation time — the
        // audit backbone. Historical calc runs reference a version so results
        // stay reproducible even after the live plan is edited.
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');             // full plan + tiers + reward rules + terms
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'version_number']);
        });

        Schema::create('plan_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('threshold_from', 20, 4)->default(0);
            $table->decimal('threshold_to', 20, 4)->nullable();      // null = open-ended top tier
            $table->string('kind')->default('rate');                 // rate (%) | amount (fixed)
            $table->decimal('rate_or_amount', 20, 6)->default(0);    // e.g. 0.05 for 5%, or fixed cash
            $table->boolean('is_cumulative')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order']);
        });

        Schema::create('reward_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            // cash_fixed | cash_pct_revenue | cash_pct_profit | cash_pct_salary
            // | badge | email | announcement | prize
            $table->string('reward_type');
            $table->decimal('value', 20, 6)->nullable();
            $table->json('meta')->nullable();     // e.g. badge label, prize description
            $table->timestamps();

            $table->index(['plan_id', 'reward_type']);
        });

        Schema::create('plan_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->longText('body');             // legal text
            $table->timestamps();

            $table->unique(['plan_id', 'version']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->text('signature')->nullable();       // typed-name signature (MVP)
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('plan_terms');
        Schema::dropIfExists('reward_rules');
        Schema::dropIfExists('plan_tiers');
        Schema::dropIfExists('plan_versions');
        Schema::dropIfExists('plans');
    }
};
