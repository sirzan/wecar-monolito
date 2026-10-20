<?php

namespace App\Services\ExternalSearch\Providers;

use App\Models\ExternalSearchDebug;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use Illuminate\Support\Facades\Http;

class ApifyFacebookProvider
{
    private const ACTOR = 'apify~facebook-marketplace-scraper';

    public function __construct(private readonly ExternalCarNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $platform): array
    {
        $token = (string) config('services.apify.token');

        if ($token === '' || ! config('services.apify.facebook_enabled')) {
            return [];
        }

        $location = trim((string) config('services.apify.facebook_location', 'mexicocity'));
        $base = (string) (config('services.apify.facebook_search_url') ?: 'https://www.facebook.com/marketplace/'.$location.'/search/');
        $searchUrl = $base.(str_contains($base, '?') ? '&' : '?').'query='.urlencode($query.' autos');
        $limit = max(1, min(50, (int) config('services.apify.facebook_results_limit', 12)));
        $timeout = max(30, min(300, (int) config('services.apify.facebook_timeout', 90)));

        try {
            $response = Http::timeout($timeout + 10)
                ->post('https://api.apify.com/v2/acts/'.self::ACTOR.'/run-sync-get-dataset-items?token='.urlencode($token).'&timeout='.$timeout, [
                    'startUrls' => [['url' => $searchUrl]],
                    'resultsLimit' => $limit,
                    'includeListingDetails' => (bool) config('services.apify.facebook_include_details', false),
                ]);

            if (! $response->successful()) {
                report(new \RuntimeException('Apify '.$response->status().' (Facebook)'));

                return [];
            }

            $items = $response->json();

            if (! is_array($items) || ! array_is_list($items)) {
                $items = is_array($items['items'] ?? null) ? $items['items'] : [];
            }

            if ($items === []) {
                return [];
            }

            $this->persistDebug($query, $items);

            $cars = [];

            foreach ($items as $item) {
                if (! is_array($item) || ($item['is_sold'] ?? false) || ($item['is_pending'] ?? false) || ($item['is_hidden'] ?? false)) {
                    continue;
                }

                $title = trim((string) ($item['marketplace_listing_title'] ?? $item['custom_title'] ?? ''));

                if ($title === '') {
                    continue;
                }

                $listingUrl = $item['listingUrl'] ?? (! empty($item['id']) ? 'https://www.facebook.com/marketplace/item/'.$item['id'] : '');

                $locationData = data_get($item, 'location.reverse_geocode');
                $locationText = collect([
                    data_get($locationData, 'city'),
                    data_get($locationData, 'state'),
                ])->filter()->implode(', ');

                if ($locationText === '') {
                    $locationText = (string) data_get($locationData, 'city_page.display_name', '');
                }

                $photos = $this->extractImages($item);

                $car = $this->normalizer->normalize([
                    'title' => $title,
                    'price' => $this->parsePrice($item['listing_price'] ?? null),
                    'year' => $this->yearFromTitle($title),
                    'km' => $this->extractKm($item),
                    'location' => $locationText ?: null,
                    'imageUrl' => $photos[0] ?? null,
                    'gallery' => array_slice($photos, 1),
                    'sourceUrl' => $listingUrl,
                ], $platform, true);

                if ($car) {
                    $cars[$car['id']] = $car;
                }
            }

            return array_slice(array_values($cars), 0, 8);
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function extractImages(array $item): array
    {
        $extract = function (mixed $photo): ?string {
            if (is_string($photo)) {
                return $this->normalizer->cleanImageUrl($photo);
            }

            if (! is_array($photo)) {
                return null;
            }

            return $this->normalizer->cleanImageUrl(
                $photo['photo_image_url'] ?? $photo['uri'] ?? data_get($photo, 'image.uri'),
            );
        };

        $candidates = [
            $extract($item['primary_listing_photo'] ?? null),
            ...array_map($extract, is_array($item['listing_photos'] ?? null) ? $item['listing_photos'] : []),
            $extract($item['image'] ?? null),
            ...array_map($extract, is_array($item['images'] ?? null) ? $item['images'] : []),
            ...array_map($extract, is_array($item['photos'] ?? null) ? $item['photos'] : []),
        ];

        return collect($candidates)->filter()->unique()->values()->all();
    }

    private function parsePrice(mixed $price): ?int
    {
        if (! is_array($price)) {
            return null;
        }

        $raw = $price['amount'] ?? $price['formatted_amount'] ?? $price['amount_with_offset_in_currency'] ?? null;

        if ($raw === null) {
            return null;
        }

        $amount = is_numeric($raw) ? (float) $raw : (float) preg_replace('/[^\d.]/', '', (string) $raw);

        return $amount > 0 ? (int) round($amount) : null;
    }

    private function extractKm(array $item): ?int
    {
        $subtitles = $item['custom_sub_titles_with_rendering_flags'] ?? [];

        if (! is_array($subtitles)) {
            return null;
        }

        foreach ($subtitles as $subtitle) {
            $text = (string) ($subtitle['subtitle'] ?? '');

            if (preg_match('/([\d.,]+)\s*([kK])?\s*km/i', $text, $match)) {
                $km = (float) str_replace([',', '.'], '', $match[1]);

                if ($km > 0) {
                    return (int) round(($match[2] ?? '') !== '' ? $km * 1000 : $km);
                }
            }
        }

        return null;
    }

    private function yearFromTitle(string $title): ?int
    {
        preg_match('/\b(19[89]\d|20[0-4]\d)\b/', $title, $match);
        $year = isset($match[1]) ? (int) $match[1] : null;

        return $year && $year >= 1995 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    private function persistDebug(string $query, array $items): void
    {
        if (! config('external_search.debug')) {
            return;
        }

        try {
            ExternalSearchDebug::query()->create([
                'provider' => 'apify-facebook',
                'query_text' => $query,
                'item_count' => count($items),
                'payload' => $items,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
