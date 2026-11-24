<?php

use App\Models\ExternalListing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        ExternalListing::query()
            ->select('source_url')
            ->whereNotNull('source_url')
            ->groupBy('source_url')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('source_url')
            ->each(function (string $sourceUrl): void {
                $duplicates = ExternalListing::query()
                    ->where('source_url', $sourceUrl)
                    ->orderByDesc('last_seen_at')
                    ->orderByDesc('updated_at')
                    ->get();

                $duplicates->slice(1)->each->delete();
            });

        Schema::table('external_listings', function (Blueprint $table): void {
            $table->unique('source_url', 'external_listings_source_url_unique');
        });
    }

    public function down(): void
    {
        Schema::table('external_listings', function (Blueprint $table): void {
            $table->dropUnique('external_listings_source_url_unique');
        });
    }
};
