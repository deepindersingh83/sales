<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P23 — Setup & data.
 *
 *  - products: a per-workspace product catalogue (SKU/name/category/price).
 *  - tags + taggables: lightweight polymorphic tagging for any entity, used by
 *    global search and reporting cuts.
 *  - workspaces: white-label columns (display name / accent colour / logo).
 *  - import_sources: recurring-import scheduling (cadence + source path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('list_price', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'sku']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
            $table->string('brand_color', 20)->nullable()->after('brand_name');
            $table->string('logo_url')->nullable()->after('brand_color');
        });

        Schema::table('import_sources', function (Blueprint $table) {
            $table->string('schedule')->nullable()->after('type');   // manual|hourly|daily|weekly
            $table->string('source_path')->nullable()->after('schedule');
            $table->timestamp('next_run_at')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'source_path', 'next_run_at']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'brand_color', 'logo_url']);
        });

        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('products');
    }
};
