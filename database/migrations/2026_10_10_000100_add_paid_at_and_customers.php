<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - transactions.paid_at: when the customer actually paid (Xero
 *   FullyPaidOnDate). Pay-when-paid plans count a deal in the period its cash
 *   arrived instead of its invoice month.
 * - customers: the customer list synced from Xero contacts, which can be
 *   tagged and assigned to a rep before (or without) being invoiced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('paid_at')->nullable()->after('is_paid');
            $table->index(['workspace_id', 'paid_at']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_system')->default('xero');
            $table->string('external_id');                 // Xero ContactID
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');   // active | archived
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('alias_id')->nullable()->constrained('aliases')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'source_system', 'external_id']);
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
