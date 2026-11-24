<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('consecutive')->unique();
            $table->string('folio')->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30)->nullable();

            $table->string('car_brand', 120);
            $table->string('car_model', 120);
            $table->string('car_version', 160)->nullable();
            $table->unsignedSmallInteger('car_year');
            $table->unsignedInteger('car_km')->nullable();

            $table->decimal('car_price', 12, 2);
            $table->decimal('service_fee_rate', 5, 4);
            $table->decimal('service_fee', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('MXN');

            $table->string('status', 20)->default('issued');
            $table->json('includes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_quotes');
    }
};
