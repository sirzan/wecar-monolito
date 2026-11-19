<?php

namespace App\Support;

use App\Models\ExternalListing;

class ExternalListingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function car(ExternalListing $listing): array
    {
        $images = collect($listing->images ?? [])
            ->filter(fn ($image) => is_string($image) && $image !== '')
            ->unique()
            ->values();

        $raw = $listing->raw_data ?? [];
        $description = trim((string) ($raw['description'] ?? ''));

        if ($description === '') {
            $description = 'Anuncio publicado en '.$listing->source.'. Revisa los detalles y confirma la información directamente con el vendedor antes de cualquier pago.';
        }

        return [
            'id' => $listing->external_id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'brand' => $listing->make,
            'brand_id' => null,
            'model' => $listing->model,
            'model_id' => null,
            'version' => null,
            'version_id' => null,
            'year' => (int) ($listing->year ?? 0),
            'km' => $listing->mileage,
            'price' => (float) ($listing->price ?? 0),
            'previous_price' => null,
            'city' => $listing->location,
            'state' => null,
            'description' => $description,
            'image' => $images->first(),
            'gallery' => $images->all(),
            'transmission' => null,
            'fuel' => null,
            'body' => null,
            'color' => null,
            'interior_color' => null,
            'invoice_type' => null,
            'insurance_status' => null,
            'accepts_trade' => false,
            'status' => 'external',
            'status_label' => 'Externo',
            'owner_name' => $listing->source,
            'agency' => null,
            'published_at' => $listing->last_seen_at?->toIso8601String(),
            'url' => route('external.detail', $listing->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function external(ExternalListing $listing): array
    {
        return [
            'sourceName' => $listing->source,
            'sourceUrl' => $listing->source_url,
            'lastSeenAt' => $listing->last_seen_at?->toIso8601String(),
            'stale' => $listing->isStale(),
            'priceAvailable' => $listing->price !== null && (float) $listing->price > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function card(ExternalListing $listing): array
    {
        $images = collect($listing->images ?? [])
            ->filter(fn ($image) => is_string($image) && $image !== '')
            ->unique()
            ->values();

        $platform = collect(config('external_search.platforms', []))
            ->firstWhere('id', $listing->source);

        return [
            'id' => $listing->external_id,
            'slug' => $listing->slug,
            'url' => route('external.detail', $listing->slug),
            'title' => $listing->title,
            'price' => $listing->price !== null ? (float) $listing->price : null,
            'year' => $listing->year,
            'km' => $listing->mileage,
            'location' => $listing->location,
            'imageUrl' => $images->first(),
            'gallery' => $images->slice(1)->values()->all(),
            'sourceUrl' => $listing->source_url,
            'platformId' => $listing->source,
            'platformName' => (string) ($platform['name'] ?? $listing->source),
            'platformColor' => (string) ($platform['color'] ?? '#0f1729'),
            'platformInitials' => (string) ($platform['initials'] ?? strtoupper(substr($listing->source, 0, 2))),
        ];
    }
}
