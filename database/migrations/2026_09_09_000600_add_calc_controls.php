<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculation controls: per-transaction exceptions (exclude), pay-when-you-get-
 * paid gating, and a manager approval step before rewards can be released.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('excluded')->default(false)->after('raw_data');
            $table->boolean('is_paid')->default(true)->after('excluded');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('pay_when_paid')->default(false)->after('filter_value');
        });

        Schema::table('calc_runs', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('completed_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calc_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
        });
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('pay_when_paid'));
        Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn(['excluded', 'is_paid']));
    }
};
