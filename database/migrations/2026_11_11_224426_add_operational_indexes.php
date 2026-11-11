<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['status', 'ends_at'], 'subscriptions_status_ends_at_index');
            $table->index(
                ['user_id', 'kind', 'status', 'ends_at'],
                'subscriptions_user_kind_status_ends_at_index',
            );
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->index(['is_active', 'plan_expires_at'], 'agencies_active_expires_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['subscription_id', 'provider', 'status'], 'payments_subscription_provider_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_subscription_provider_status_index');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropIndex('agencies_active_expires_index');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_user_kind_status_ends_at_index');
            $table->dropIndex('subscriptions_status_ends_at_index');
        });
    }
};
