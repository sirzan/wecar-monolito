<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('type')->default('publication')->after('key_name');
            $table->unsignedInteger('token_amount')->nullable()->after('max_cars');
            $table->index('type');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('kind')->default('publication')->after('user_id');
            $table->index('kind');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('subscription_id')->constrained('plans')->nullOnDelete();
            $table->unique(['provider', 'provider_reference']);
        });

        DB::table('plans')->where('key_name', 'membresia_ext_499')->update(['type' => 'membership']);
        DB::table('plans')->where('key_name', 'like', 'search_tokens_%')->update(['type' => 'tokens']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_reference']);
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'token_amount']);
        });
    }
};
