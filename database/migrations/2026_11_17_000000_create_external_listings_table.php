<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id', 32)->unique();
            $table->string('source');
            $table->text('source_url');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('location')->nullable();
            $table->json('images')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'last_seen_at']);
            $table->index(['make', 'model', 'year']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_listings');
    }
};
