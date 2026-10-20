<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_search_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('query_key')->unique();
            $table->text('query_text');
            $table->json('platforms');
            $table->json('results');
            $table->json('platforms_searched');
            $table->timestamps();

            $table->index(['query_key', 'updated_at']);
        });

        Schema::create('external_search_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip_address')->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('search_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->date('usage_date');
            $table->timestamps();

            $table->index('usage_date');
        });

        Schema::create('external_search_user_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('search_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->date('usage_date');
            $table->timestamps();

            $table->index('usage_date');
        });

        Schema::create('external_search_debug', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->text('query_text')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_search_debug');
        Schema::dropIfExists('external_search_user_usage');
        Schema::dropIfExists('external_search_usage');
        Schema::dropIfExists('external_search_cache');
    }
};
