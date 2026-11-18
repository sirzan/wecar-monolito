<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->foreignUuid('car_id')->nullable()->change();
            $table->foreignUuid('external_listing_id')
                ->nullable()
                ->after('car_id')
                ->constrained('external_listings')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'external_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'external_listing_id']);
            $table->dropConstrainedForeignId('external_listing_id');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->foreignUuid('car_id')->nullable(false)->change();
        });
    }
};
