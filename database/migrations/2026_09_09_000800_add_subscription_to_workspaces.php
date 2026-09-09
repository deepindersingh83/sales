<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P24 — SaaS billing. Each workspace carries its subscription tier, trial
 * window and (scaffolded) Stripe customer/subscription references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('subscription_tier')->default('free')->after('logo_url');
            $table->string('subscription_status')->default('active')->after('subscription_tier'); // active|past_due|canceled
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->string('stripe_customer_id')->nullable()->after('trial_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_tier',
                'subscription_status',
                'trial_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id',
            ]);
        });
    }
};
