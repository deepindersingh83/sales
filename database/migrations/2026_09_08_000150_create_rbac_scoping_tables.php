<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-scoping tables:
 *  - plan_admin_assignments: which plans a Plan Admin may control.
 *  - plan_visibility_hides:   plans explicitly hidden from a given Limited Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'user_id']);
        });

        Schema::create('plan_visibility_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // limited admin hidden from
            $table->timestamps();

            $table->unique(['plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_visibility_hides');
        Schema::dropIfExists('plan_admin_assignments');
    }
};
