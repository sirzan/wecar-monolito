<?php

namespace App\Services\ExternalSearch;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarVersion;
use App\Models\ExternalListing;
use App\Models\ExternalSearchCache;
use App\Support\ExternalListingSlug;
use Illuminate\Database\Eloquent\Builder;
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
     * Filtra y ordena los autos externos almacenados en external_listings usando los mismos filtros de /comprar.
     *
     * @param  array<string, mixed>  $filters
     * @return array{results: array<int, array<string, mixed>>, total: int}
     */
    public function searchListings(array $filters = [], int $limit = 24): array
    {
        if (ExternalListing::query()->doesntExist()) {
            $this->recentCars(60);
        }

        $query = ExternalListing::query()
            ->where(function (Builder $builder): void {
                $builder->where('last_seen_at', '>=', now()->subDays($this->days()))
                    ->orWhereNull('last_seen_at');
            });

        $brandSlug = is_string($filters['brand'] ?? null) ? trim($filters['brand']) : '';

        if ($brandSlug !== '') {
            $brandModel = CarBrand::query()->where('slug', $brandSlug)->first(['name', 'slug']);
            $brandName = $brandModel?->name ?? str_replace('-', ' ', $brandSlug);
            $slugNormalized = Str::slug($brandSlug);

            $query->where(function (Builder $builder) use ($slugNormalized, $brandName): void {
                $builder->where('make', $slugNormalized)
                    ->orWhereLike('make', '%'.$slugNormalized.'%')
                    ->orWhereLike('title', '%'.$brandName.'%');
            });
        }

        $modelId = is_string($filters['model'] ?? null) ? trim($filters['model']) : '';

        if ($modelId !== '') {
            $carModel = CarModel::query()->find($modelId, ['name', 'slug']);

            if ($carModel !== null) {
                $modelSlug = Str::slug($carModel->slug ?: $carModel->name);
                $modelName = trim($carModel->name);

                $query->where(function (Builder $builder) use ($modelSlug, $modelName): void {
                    $builder->whereLike('model', '%'.$modelSlug.'%')
                        ->orWhereLike('title', '%'.$modelName.'%');
                });
            }
        }

        $versionId = is_string($filters['version'] ?? null) ? trim($filters['version']) : '';

        if ($versionId !== '') {
            $carVersion = CarVersion::query()->find($versionId, ['name']);

            if ($carVersion !== null && trim($carVersion->name) !== '') {
                $versionTokens = array_values(array_filter(
                    preg_split('/[\s\/-]+/u', trim($carVersion->name)) ?: [],
                    static fn (string $token): bool => mb_strlen($token) >= 2,
                ));

                foreach (array_slice($versionTokens, 0, 3) as $token) {
                    $query->where(function (Builder $builder) use ($token): void {
                        $builder->whereLike('title', '%'.$token.'%')
                            ->orWhereLike('slug', '%'.Str::slug($token).'%');
                    });
                }
            }
        }

        $city = is_string($filters['city'] ?? null) ? trim($filters['city']) : '';

        if ($city !== '') {
            $cityVariants = $this->cityVariants($city);

            $query->where(function (Builder $builder) use ($cityVariants): void {
                foreach ($cityVariants as $variant) {
                    $builder->orWhereLike('location', '%'.$variant.'%')
                        ->orWhereLike('title', '%'.$variant.'%');
                }
            });
        }

        foreach (['body', 'transmission', 'fuel', 'color'] as $attribute) {
            $value = is_string($filters[$attribute] ?? null) ? trim($filters[$attribute]) : '';

            if ($value === '') {
                continue;
            }

            $keywords = $this->attributeKeywords($attribute, $value);

            $query->where(function (Builder $builder) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $builder->orWhereLike('title', '%'.$keyword.'%')
                        ->orWhereLike('slug', '%'.Str::slug($keyword).'%');
                }
            });
        }

        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->whereNotNull('price')->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->whereNotNull('price')->where('price', '<=', (float) $filters['max_price']);
        }

        if (isset($filters['min_year']) && is_numeric($filters['min_year'])) {
            $query->whereNotNull('year')->where('year', '>=', (int) $filters['min_year']);
        }

        if (isset($filters['max_year']) && is_numeric($filters['max_year'])) {
            $query->whereNotNull('year')->where('year', '<=', (int) $filters['max_year']);
        }

        if (isset($filters['min_km']) && is_numeric($filters['min_km'])) {
            $query->whereNotNull('mileage')->where('mileage', '>=', (int) $filters['min_km']);
        }

        if (isset($filters['max_km']) && is_numeric($filters['max_km'])) {
            $query->whereNotNull('mileage')->where('mileage', '<=', (int) $filters['max_km']);
        }

        $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';

        if ($q !== '') {
            $tokens = array_values(array_filter(
                preg_split('/[\s,]+/u', $q) ?: [],
                static fn (string $token): bool => mb_strlen($token) >= 2,
            ));

            foreach (array_slice($tokens, 0, 6) as $token) {
                $slugToken = Str::slug($token);

                $query->where(function (Builder $builder) use ($token, $slugToken): void {
                    $builder->whereLike('title', '%'.$token.'%')
                        ->orWhereLike('location', '%'.$token.'%');

                    if ($slugToken !== '') {
                        $builder->orWhereLike('make', '%'.$slugToken.'%')
                            ->orWhereLike('model', '%'.$slugToken.'%')
                            ->orWhereLike('slug', '%'.$slugToken.'%');
                    }

                    if (preg_match('/^(19|20)\d{2}$/', $token) === 1) {
                        $builder->orWhere('year', (int) $token);
                    }
                });
            }
        }

        $total = (clone $query)->count();

        match ($filters['sort'] ?? 'recent') {
            'price-asc' => $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END ASC')->orderBy('price'),
            'price-desc' => $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END ASC')->orderByDesc('price'),
            'year-desc' => $query->orderByRaw('CASE WHEN year IS NULL THEN 1 ELSE 0 END ASC')->orderByDesc('year'),
            'km-asc' => $query->orderByRaw('CASE WHEN mileage IS NULL THEN 1 ELSE 0 END ASC')->orderBy('mileage'),
            default => $query->orderByDesc('last_seen_at')->orderByDesc('updated_at'),
        };

        $rows = $query->limit(max(1, min(60, $limit)))->get();

        return [
            'results' => $this->formatListingRows($rows),
            'total' => $total,
        ];
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

        $cars = $this->formatListingRows($rows);

        return array_slice($this->normalizer->sortByPlatform($cars), 0, $limit);
    }

    /**
     * @param  Collection<int, ExternalListing>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatListingRows(Collection $rows): array
    {
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
                'slug' => (string) $row->slug,
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

        return $cars;
    }

    /**
     * @return array<int, string>
     */
    private function cityVariants(string $city): array
    {
        $primary = trim(explode(',', $city)[0] ?? $city);
        $lower = Str::lower(Str::ascii($primary));

        if (in_array($lower, ['cdmx', 'ciudad de mexico', 'distrito federal'], true)) {
            return ['CDMX', 'Ciudad de México', 'Ciudad de Mexico', 'Distrito Federal'];
        }

        if ($lower === 'estado de mexico' || $lower === 'edomex') {
            return ['Estado de México', 'Estado de Mexico', 'Edomex'];
        }

        return array_values(array_unique(array_filter([$city, $primary])));
    }

    /**
     * @return array<int, string>
     */
    private function attributeKeywords(string $attribute, string $value): array
    {
        $lower = Str::lower(Str::ascii($value));

        return match ($attribute) {
            'transmission' => match (true) {
                str_contains($lower, 'auto') || str_contains($lower, 'cvt') => [$value, 'aut', 'automatico', 'automatica', 'cvt', 'dsg', 'tiptronic'],
                str_contains($lower, 'man') || str_contains($lower, 'estandar') => [$value, 'manual', 'estandar', 'std'],
                default => [$value],
            },
            'fuel' => match (true) {
                str_contains($lower, 'hibrid') || str_contains($lower, 'hybrid') => [$value, 'hibrido', 'híbrido', 'hybrid', 'hev', 'phev'],
                str_contains($lower, 'electr') => [$value, 'electrico', 'eléctrico', 'ev'],
                str_contains($lower, 'diesel') => [$value, 'diesel', 'diésel', 'tdi'],
                default => [$value],
            },
            'body' => match (true) {
                str_contains($lower, 'suv') => [$value, 'suv', 'camioneta'],
                str_contains($lower, 'sedan') => [$value, 'sedan', 'sedán'],
                str_contains($lower, 'pickup') || str_contains($lower, 'pick') => [$value, 'pickup', 'pick up', 'cabina'],
                str_contains($lower, 'hatch') => [$value, 'hatchback', 'hb'],
                str_contains($lower, 'coupe') => [$value, 'coupe', 'coupé'],
                str_contains($lower, 'minivan') => [$value, 'minivan', 'van'],
                default => [$value],
            },
            default => [$value],
        };
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
