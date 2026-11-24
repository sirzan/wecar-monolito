<?php

namespace App\Services\ExternalSearch;

use App\Models\ExternalListing;
use App\Models\ExternalSearchCache;
use App\Support\ExternalListingSlug;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExternalSearchCacheService
{
    public function __construct(private readonly ExternalCarNormalizer $normalizer) {}

    /**
     * @param  array<int, array<string, mixed>>  $platforms
     */
    public function key(string $query, array $platforms): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(trim($query))) ?? '';
        $words = array_values(array_filter(preg_split('/\s+/u', $normalized) ?: []));
        sort($words);

        $platformIds = collect($platforms)->pluck('id')->sort()->implode(',');

        return 'ext|'.implode(' ', $words).'|'.$platformIds;
    }

    public function days(): int
    {
        return max(1, (int) config('external_search.cache_days', 7));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $queryKey): ?array
    {
        $row = ExternalSearchCache::query()
            ->where('query_key', $queryKey)
            ->where('updated_at', '>=', now()->subDays($this->days()))
            ->first();

        return $row?->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $platforms
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<int, string>  $platformsSearched
     */
    public function put(string $queryKey, string $queryText, array $platforms, array $results, array $platformsSearched): void
    {
        if ($results === []) {
            return;
        }

        $clean = $this->cleanResults($results);

        if ($clean === []) {
            return;
        }

        ExternalSearchCache::query()->updateOrCreate(
            ['query_key' => $queryKey],
            [
                'query_text' => $queryText,
                'platforms' => $platforms,
                'results' => $clean,
                'platforms_searched' => $platformsSearched,
            ],
        );

        $this->upsertListings($clean);
    }

    /**
     * Sanea títulos y deduplica por URL antes de persistir.
     *
     * @param  array<int, mixed>  $results
     * @return array<int, array<string, mixed>>
     */
    private function cleanResults(array $results): array
    {
        $clean = [];

        foreach ($results as $car) {
            if (! is_array($car)) {
                continue;
            }

            $sourceUrl = $this->normalizer->cleanUrl((string) ($car['sourceUrl'] ?? ''));

            if (! $sourceUrl) {
                continue;
            }

            $title = $this->normalizer->sanitizeListingTitle((string) ($car['title'] ?? ''), $sourceUrl);

            if ($title === '') {
                continue;
            }

            $car['sourceUrl'] = $sourceUrl;
            $car['title'] = $title;
            $car['id'] = $this->normalizer->externalId($sourceUrl);

            $clean[$sourceUrl] = $car;
        }

        return array_values($clean);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function upsertListings(array $results): void
    {
        foreach ($results as $car) {
            if (! is_array($car)) {
                continue;
            }

            $sourceUrl = $this->normalizer->cleanUrl((string) ($car['sourceUrl'] ?? ''));

            if (! $sourceUrl) {
                continue;
            }

            $externalId = $this->normalizer->externalId($sourceUrl);
            $title = $this->normalizer->sanitizeListingTitle((string) ($car['title'] ?? ''), $sourceUrl);

            if ($title === '') {
                continue;
            }

            $year = is_numeric($car['year'] ?? null) ? (int) $car['year'] : null;
            $make = ExternalListingSlug::makeFromTitle($title);
            $model = ExternalListingSlug::modelFromTitle($title, $make);

            ExternalListing::query()->updateOrCreate(
                ['source_url' => $sourceUrl],
                [
                    'external_id' => $externalId,
                    'source' => (string) ($car['platformId'] ?? $car['platformName'] ?? 'externo'),
                    'slug' => ExternalListingSlug::make($year, $title, $externalId),
                    'title' => $title,
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'price' => is_numeric($car['price'] ?? null) ? (float) $car['price'] : null,
                    'mileage' => is_numeric($car['km'] ?? null) ? max(0, (int) $car['km']) : null,
                    'location' => filled($car['location'] ?? null) ? (string) $car['location'] : null,
                    'images' => array_values(array_filter([
                        $car['imageUrl'] ?? null,
                        ...($car['gallery'] ?? []),
                    ])),
                    'raw_data' => $car,
                    'last_seen_at' => now(),
                ],
            );
        }
    }

    /**
     * @return Collection<int, ExternalSearchCache>
     */
    public function recent(int $limit = 50): Collection
    {
        return ExternalSearchCache::query()
            ->where('updated_at', '>=', now()->subDays($this->days()))
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function findUrlById(string $id): ?string
    {
        foreach ($this->recent(100) as $row) {
            foreach ($row->results ?? [] as $car) {
                if (! is_array($car) || empty($car['sourceUrl'])) {
                    continue;
                }

                $carId = $car['id'] ?? $this->normalizer->externalId((string) $car['sourceUrl']);

                if ($carId === $id) {
                    return (string) $car['sourceUrl'];
                }
            }
        }

        return null;
    }

    /**
     * Autos externos válidos de las últimas búsquedas, deduplicados por ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentCars(int $limit = 24): array
    {
        $cars = [];
        $seen = [];

        foreach ($this->recent(100) as $row) {
            foreach ($row->results ?? [] as $rawCar) {
                if (! is_array($rawCar)) {
                    continue;
                }

                $platform = collect(config('external_search.platforms', []))
                    ->firstWhere('id', $rawCar['platformId'] ?? '');

                if (! $platform) {
                    continue;
                }

                $car = $this->normalizer->normalize($rawCar, $platform, true);

                if (! $car || isset($seen[$car['id']]) || $this->normalizer->isPlaceholderUrl((string) ($car['sourceUrl'] ?? ''))) {
                    continue;
                }

                $seen[$car['id']] = true;
                $cars[] = $car;
            }
        }

        $cars = array_slice($this->normalizer->sortByPlatform($cars), 0, $limit);

        if ($cars === []) {
            return $this->listingsFallback($limit);
        }

        // Compatibilidad con cachés creadas antes de external_listings.
        $known = ExternalListing::query()
            ->whereIn('external_id', collect($cars)->pluck('id')->filter()->all())
            ->pluck('external_id')
            ->all();

        $missing = array_values(array_filter($cars, fn (array $car) => ! in_array($car['id'] ?? null, $known, true)));

        if ($missing !== []) {
            $this->upsertListings($missing);
        }

        return $cars;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminCars(ExternalSearchCache $cache): array
    {
        $cars = [];

        foreach ($cache->results ?? [] as $rawCar) {
            if (! is_array($rawCar)) {
                continue;
            }

            $car = $this->adminCar($rawCar);

            if ($car && ! isset($cars[$car['id']])) {
                $cars[$car['id']] = $car;
            }
        }

        return array_values($cars);
    }

    /**
     * Removes external cars from every active search group where they appear.
     *
     * @param  array<int, string>  $ids
     */
    public function deleteCars(array $ids): int
    {
        $ids = array_fill_keys(array_values(array_unique($ids)), true);
        $removed = 0;

        ExternalSearchCache::query()->chunkById(100, function ($groups) use (&$removed, $ids): void {
            foreach ($groups as $group) {
                $remaining = [];

                foreach ($group->results ?? [] as $rawCar) {
                    if (! is_array($rawCar) || ! $this->matchesAnyId($rawCar, $ids)) {
                        $remaining[] = $rawCar;

                        continue;
                    }

                    $removed++;
                }

                if (count($remaining) === count($group->results ?? [])) {
                    continue;
                }

                $this->forgetMemoryCache($group);

                if ($remaining === []) {
                    $group->delete();
                } else {
                    $group->update(['results' => array_values($remaining)]);
                }
            }
        });

        return $removed;
    }

    public function deleteGroup(ExternalSearchCache $group): void
    {
        $this->forgetMemoryCache($group);
        $group->delete();
    }

    public function prune(): void
    {
        ExternalSearchCache::query()
            ->where('updated_at', '<', Carbon::now()->subDays($this->days()))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $rawCar
     * @return array<string, mixed>|null
     */
    private function adminCar(array $rawCar): ?array
    {
        $sourceUrl = $this->normalizer->cleanUrl((string) ($rawCar['sourceUrl'] ?? ''));

        if (! $sourceUrl) {
            return null;
        }

        return [
            'id' => $this->normalizer->externalId($sourceUrl),
            'title' => $this->normalizer->sanitizeListingTitle((string) ($rawCar['title'] ?? ''), $sourceUrl),
            'price' => is_numeric($rawCar['price'] ?? null) ? (int) round((float) $rawCar['price']) : null,
            'year' => is_numeric($rawCar['year'] ?? null) ? (int) $rawCar['year'] : null,
            'km' => is_numeric($rawCar['km'] ?? null) ? max(0, (int) $rawCar['km']) : null,
            'location' => filled($rawCar['location'] ?? null) ? (string) $rawCar['location'] : null,
            'imageUrl' => $rawCar['imageUrl'] ?? null,
            'sourceUrl' => $sourceUrl,
            'platformId' => (string) ($rawCar['platformId'] ?? ''),
            'platformName' => (string) ($rawCar['platformName'] ?? $rawCar['platformId'] ?? 'Externo'),
        ];
    }

    /**
     * Respaldo de visibilidad: usa los listings persistidos cuando no hay caché vigente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listingsFallback(int $limit): array
    {
        $rows = ExternalListing::query()
            ->where('last_seen_at', '>=', now()->subDays($this->days()))
            ->latest('last_seen_at')
            ->limit(max($limit * 3, 60))
            ->get();

        $cars = [];
        $seen = [];
        $platforms = collect(config('external_search.platforms', []))->keyBy('id');

        foreach ($rows as $row) {
            $sourceUrl = (string) $row->source_url;
            $title = $this->normalizer->sanitizeListingTitle((string) $row->title, $sourceUrl);

            if ($title === '' || isset($seen[$row->external_id])) {
                continue;
            }

            $platform = $platforms->get((string) $row->source);
            $images = is_array($row->images) ? array_values(array_filter($row->images, 'is_string')) : [];

            $seen[$row->external_id] = true;
            $cars[] = [
                'id' => (string) $row->external_id,
                'title' => $title,
                'price' => $row->price !== null ? (int) round((float) $row->price) : null,
                'year' => $row->year,
                'km' => $row->mileage,
                'location' => $row->location,
                'imageUrl' => $images[0] ?? null,
                'gallery' => array_slice($images, 1),
                'sourceUrl' => $sourceUrl,
                'platformId' => (string) $row->source,
                'platformName' => (string) ($platform['name'] ?? Str::headline((string) $row->source)),
                'platformColor' => (string) ($platform['color'] ?? ''),
                'platformInitials' => (string) ($platform['initials'] ?? Str::upper(Str::substr((string) $row->source, 0, 2))),
            ];
        }

        return array_slice($this->normalizer->sortByPlatform($cars), 0, $limit);
    }

    /**
     * Repara títulos, make/model/slug de listings y títulos dentro de la caché.
     *
     * @return array{listings:int, caches:int}
     */
    public function repairListings(): array
    {
        $listingsFixed = 0;

        ExternalListing::query()->chunkById(200, function ($listings) use (&$listingsFixed): void {
            foreach ($listings as $listing) {
                $sourceUrl = (string) $listing->source_url;
                $externalId = $this->normalizer->externalId($sourceUrl);
                $title = $this->normalizer->sanitizeListingTitle((string) $listing->title, $sourceUrl);

                if ($title === '') {
                    continue;
                }

                $make = ExternalListingSlug::makeFromTitle($title);
                $model = ExternalListingSlug::modelFromTitle($title, $make);

                $listing->update([
                    'external_id' => $externalId,
                    'title' => $title,
                    'make' => $make,
                    'model' => $model,
                    'slug' => ExternalListingSlug::make($listing->year, $title, $externalId),
                ]);

                $listingsFixed++;
            }
        });

        $cachesFixed = 0;

        ExternalSearchCache::query()->chunkById(50, function ($rows) use (&$cachesFixed): void {
            foreach ($rows as $row) {
                $clean = $this->cleanResults($row->results ?? []);
                $row->update(['results' => $clean]);
                $this->forgetMemoryCache($row);
                $cachesFixed++;
            }
        });

        return ['listings' => $listingsFixed, 'caches' => $cachesFixed];
    }

    /**
     * @param  array<string, mixed>  $rawCar
     * @param  array<string, bool>  $ids
     */
    private function matchesAnyId(array $rawCar, array $ids): bool
    {
        $sourceUrl = $this->normalizer->cleanUrl((string) ($rawCar['sourceUrl'] ?? ''));
        $id = (string) ($rawCar['id'] ?? '');

        return ($id !== '' && isset($ids[$id]))
            || ($sourceUrl !== null && isset($ids[$this->normalizer->externalId($sourceUrl)]));
    }

    private function forgetMemoryCache(ExternalSearchCache $group): void
    {
        Cache::forget('external-search:'.$group->query_key);
    }
}
