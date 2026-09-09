<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Currency + FX schema. Built now (Phase 1) so the multi-currency work in
 * product Phase 2 doesn't require a data-model rewrite. MVP behavior is
 * single-currency per workspace; these tables carry reference data + rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();      // ISO 4217, e.g. USD
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->timestamps();
        });

        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'effective_date'], 'fx_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
        Schema::dropIfExists('currencies');
    }
};
