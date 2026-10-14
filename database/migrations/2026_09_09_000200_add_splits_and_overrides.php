<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team mechanics:
 *  - aliases.split_percent: when several aliases match a transaction, each
 *    credits this percentage of it to its user (deal splits).
 *  - workspace_user.manager_id: the member's reporting manager (single-level).
 *  - plans.manager_override_percent: a manager earns this % of their direct
 *    reports' credited attainment as an override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliases', function (Blueprint $table) {
            $table->decimal('split_percent', 8, 4)->default(100)->after('match_type');
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('manager_override_percent', 8, 4)->nullable()->after('commission_formula');
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('manager_override_percent'));
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
        Schema::table('aliases', fn (Blueprint $t) => $t->dropColumn('split_percent'));
    }
};
