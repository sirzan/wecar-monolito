<?php

namespace App\Services\ExternalSearch;

use App\Models\ExternalSearchCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

        ExternalSearchCache::query()->updateOrCreate(
            ['query_key' => $queryKey],
            [
                'query_text' => $queryText,
                'platforms' => $platforms,
                'results' => $results,
                'platforms_searched' => $platformsSearched,
            ],
        );
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

        return array_slice($this->normalizer->sortByPlatform($cars), 0, $limit);
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
            'title' => trim((string) ($rawCar['title'] ?? 'Auto externo')),
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
