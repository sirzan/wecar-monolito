<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_token_wallets', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('search_token_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('kind');
            $table->string('reference')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('search_ad_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('coupon_recipients', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('coupon_code')->default('TKNS50');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_recipients');
        Schema::dropIfExists('search_ad_views');
        Schema::dropIfExists('search_token_transactions');
        Schema::dropIfExists('search_token_wallets');
    }
};
