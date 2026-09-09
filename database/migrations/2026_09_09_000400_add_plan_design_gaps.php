<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-design gaps: exclude-tax basis, per-plan transaction filter, salary for
 * salary-based allocations, and a calc-run mode (standard vs true-up).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('tax_rate_percent', 8, 4)->nullable()->after('performance_metric');
            $table->string('filter_field')->nullable()->after('tax_rate_percent');
            $table->string('filter_value')->nullable()->after('filter_field');
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->decimal('salary', 20, 2)->nullable()->after('manager_id');
        });

        Schema::table('calc_runs', function (Blueprint $table) {
            $table->string('mode')->default('standard')->after('is_simulation'); // standard | true_up
        });
    }

    public function down(): void
    {
        Schema::table('calc_runs', fn (Blueprint $t) => $t->dropColumn('mode'));
        Schema::table('workspace_user', fn (Blueprint $t) => $t->dropColumn('salary'));
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['tax_rate_percent', 'filter_field', 'filter_value']));
    }
};
