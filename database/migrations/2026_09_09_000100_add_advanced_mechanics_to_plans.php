<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced plan mechanics: a quota target (for attainment %), a payout cap, and
 * an optional custom commission formula that replaces tier math when set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('quota', 20, 4)->nullable()->after('performance_metric');
            $table->decimal('payout_cap', 20, 4)->nullable()->after('quota');
            $table->string('commission_formula')->nullable()->after('payout_cap');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['quota', 'payout_cap', 'commission_formula']);
        });
    }
};
