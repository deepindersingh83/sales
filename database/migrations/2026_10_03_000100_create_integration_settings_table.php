<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace integration settings managed from the Integrations page (e.g.
 * the workspace's own Xero app keys). Secrets are encrypted by the model cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider');          // e.g. "xero"
            $table->text('settings')->nullable(); // encrypted JSON
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
