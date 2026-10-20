<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key_name')->unique();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('duration')->default('30 días');
            $table->unsignedInteger('duration_weeks')->default(4);
            $table->unsignedInteger('max_cars')->default(1);
            $table->boolean('highlight')->default(false);
            $table->json('features')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('financieras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('tipo')->default('banco');
            $table->string('logo')->nullable();
            $table->string('color')->nullable();
            $table->decimal('tasa_min', 5, 2)->nullable();
            $table->decimal('tasa_max', 5, 2)->nullable();
            $table->decimal('cat_min', 5, 2)->nullable();
            $table->decimal('cat_max', 5, 2)->nullable();
            $table->unsignedInteger('enganche_pct')->nullable();
            $table->unsignedInteger('plazo_max')->nullable();
            $table->unsignedInteger('score_min')->nullable();
            $table->boolean('destacado')->default(false);
            $table->string('aplica_para')->nullable();
            $table->string('contacto')->nullable();
            $table->text('notas')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('financieras');
        Schema::dropIfExists('plans');
    }
};
