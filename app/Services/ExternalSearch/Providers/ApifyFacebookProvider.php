<?php

namespace App\Services\ExternalSearch\Providers;

use App\Models\ExternalSearchDebug;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApifyFacebookProvider
{
    private const ACTOR = 'apify~facebook-marketplace-scraper';

    /** @var array<string, string> */
    private const CITY_SLUGS = [
        'cdmx' => 'mexicocity',
        'ciudad de mexico' => 'mexicocity',
        'mexico' => 'mexicocity',
        'monterrey' => 'monterrey',
        'guadalajara' => 'guadalajara',
        'puebla' => 'puebla',
        'queretaro' => 'queretaro',
        'tijuana' => 'tijuana',
        'leon' => 'leon',
        'merida' => 'merida',
        'cancun' => 'cancun',
        'toluca' => 'toluca',
        'chihuahua' => 'chihuahua',
        'aguascalientes' => 'aguascalientes',
        'saltillo' => 'saltillo',
        'san luis potosi' => 'sanluispotosi',
        'hermosillo' => 'hermosillo',
        'veracruz' => 'veracruz',
        'morelia' => 'morelia',
        'culiacan' => 'culiacan',
        'mexicali' => 'mexicali',
        'ciudad juarez' => 'juarez',
        'juarez' => 'juarez',
        'zapopan' => 'guadalajara',
        'san pedro garza garcia' => 'monterrey',
    ];

    public function __construct(private readonly ExternalCarNormalizer $normalizer) {}

    /**
     * Inicia la búsqueda en Facebook Marketplace vía Apify de forma asíncrona para ejecutarse en paralelo con Firecrawl.
     *
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     */
    public function startAsyncSearch(string $query, array $platform, array $specs = []): ?PromiseInterface
    {
        $token = (string) config('services.apify.token');

        if ($token === '' || ! config('services.apify.facebook_enabled')) {
            return null;
        }

        $city = is_string($specs['city'] ?? null) ? $specs['city'] : null;
        $location = $this->resolveLocationSlug($city);
        $base = (string) (config('services.apify.facebook_search_url') ?: 'https://www.facebook.com/marketplace/'.$location.'/search/');
        $cleanQuery = $this->buildMarketplaceQuery($query, $specs);
        $searchUrl = $base.(str_contains($base, '?') ? '&' : '?').'query='.urlencode($cleanQuery);
        $limit = max(1, min(50, (int) config('services.apify.facebook_results_limit', 15)));
        $timeout = max(12, min(60, (int) config('services.apify.facebook_timeout', 25)));

        try {
            return Http::connectTimeout(5)
                ->timeout($timeout + 5)
                ->async()
                ->post('https://api.apify.com/v2/acts/'.self::ACTOR.'/run-sync-get-dataset-items?token='.urlencode($token).'&timeout='.$timeout, [
                    'startUrls' => [['url' => $searchUrl]],
                    'resultsLimit' => $limit,
                    'includeListingDetails' => (bool) config('services.apify.facebook_include_details', false),
                ]);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $platform, array $specs = []): array
    {
        $promise = $this->startAsyncSearch($query, $platform, $specs);

        if ($promise === null) {
            return [];
        }

        try {
            $response = $promise->wait();
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }

        return $this->parseResponse($response, $query, $platform, $specs);
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function parseResponse(mixed $response, string $query, array $platform, array $specs = []): array
    {
        if (! $response instanceof Response) {
            if ($response instanceof \Throwable) {
                report($response);
            }

            return [];
        }

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

            $listingUrl = $this->resolveListingUrl($item);

            if ($listingUrl === '') {
                continue;
            }

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
                'year' => $this->extractYear($item, $title),
                'km' => $this->extractKm($item),
                'location' => $locationText ?: null,
                'imageUrl' => $photos[0] ?? null,
                'gallery' => array_slice($photos, 1),
                'sourceUrl' => $listingUrl,
            ], $platform, true);

            if ($car && $this->normalizer->matchesQuery($car, $query, $specs)) {
                $cars[$car['id']] = $car;
            }
        }

        return array_slice(array_values($cars), 0, 12);
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    private function buildMarketplaceQuery(string $query, array $specs): string
    {
        $parts = array_values(array_filter([
            is_string($specs['brand'] ?? null) ? trim($specs['brand']) : null,
            is_string($specs['model'] ?? null) ? trim($specs['model']) : null,
            is_numeric($specs['year'] ?? null) ? (string) $specs['year'] : null,
        ]));

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return trim(preg_replace('/\s+/', ' ', $query) ?? $query);
    }

    private function resolveLocationSlug(?string $city): string
    {
        $default = trim((string) config('services.apify.facebook_location', 'mexicocity'));

        if ($city === null || trim($city) === '') {
            return $default !== '' ? $default : 'mexicocity';
        }

        $normalized = Str::lower(Str::ascii(trim($city)));
        $primary = trim(explode(',', $normalized)[0] ?? $normalized);

        if (isset(self::CITY_SLUGS[$primary])) {
            return self::CITY_SLUGS[$primary];
        }

        foreach (self::CITY_SLUGS as $cityName => $slug) {
            if (str_contains($normalized, $cityName)) {
                return $slug;
            }
        }

        return $default !== '' ? $default : 'mexicocity';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveListingUrl(array $item): string
    {
        if (! empty($item['id']) && preg_match('/^\d{6,}$/', (string) $item['id']) === 1) {
            return 'https://www.facebook.com/marketplace/item/'.$item['id'];
        }

        $rawUrl = trim((string) ($item['listingUrl'] ?? $item['url'] ?? ''));

        if ($rawUrl === '') {
            return '';
        }

        return str_replace('://m.facebook.com/', '://www.facebook.com/', $rawUrl);
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
        if (is_numeric($price)) {
            $amount = (float) $price;

            return $amount > 0 ? (int) round($amount) : null;
        }

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

    /**
     * @param  array<string, mixed>  $item
     */
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

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractYear(array $item, string $title): ?int
    {
        $subtitles = is_array($item['custom_sub_titles_with_rendering_flags'] ?? null)
            ? implode(' ', array_map(fn ($entry) => is_array($entry) ? (string) ($entry['subtitle'] ?? '') : '', $item['custom_sub_titles_with_rendering_flags']))
            : '';

        $sources = [
            $title,
            (string) ($item['custom_title'] ?? ''),
            $subtitles,
            (string) data_get($item, 'redacted_description.text', ''),
        ];

        foreach ($sources as $text) {
            $year = $this->yearFromText($text);

            if ($year !== null) {
                return $year;
            }
        }

        return null;
    }

    private function yearFromText(string $text): ?int
    {
        if ($text === '') {
            return null;
        }

        preg_match('/\b(19[89]\d|20[0-4]\d)\b/', $text, $match);
        $year = isset($match[1]) ? (int) $match[1] : null;

        return $year && $year >= 1995 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    /**
     * @param  array<int, mixed>  $items
     */
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
