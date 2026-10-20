<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignUuid('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained('car_brands')->nullOnDelete();
            $table->foreignUuid('model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->foreignUuid('version_id')->nullable()->constrained('car_versions')->nullOnDelete();
            $table->foreignUuid('status_id')->nullable()->constrained('car_statuses')->nullOnDelete();

            $table->string('slug')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('km')->default(0);
            $table->decimal('price', 12, 2);
            $table->decimal('previous_price', 12, 2)->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->string('transmission')->nullable();
            $table->string('fuel')->nullable();
            $table->string('body')->nullable();
            $table->string('color')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('accepts_trade')->default(false);
            $table->string('trade_preference')->nullable();
            $table->string('vin')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('plates')->nullable();
            $table->string('plan')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'featured']);
            $table->index(['brand_id', 'model_id']);
            $table->index(['city', 'state']);
            $table->index('price');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
