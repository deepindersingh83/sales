<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform super admins: operators who can enter any workspace as its Full
 * Admin. Granted only from the console (php artisan app:super-admin), never
 * through the web UI. If SUPER_ADMIN_EMAIL is set and that account already
 * exists, it is promoted (and its email marked verified) when this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('email_verified_at');
        });

        $email = config('app.super_admin_email');
        if ($email) {
            User::where('email', strtolower(trim($email)))->get()->each(fn (User $user) => $user->forceFill([
                'is_super_admin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save());
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
