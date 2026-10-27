<?php

namespace App\Services\CarCatalog;

use App\Models\CarModel;
use App\Models\CarVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarVersionService
{
    private const SUCCESS_TTL_HOURS = 12;

    private const ERROR_TTL_MINUTES = 5;

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function versionsFor(CarModel $model, int $year): array
    {
        $cacheKey = $this->cacheKey($model->getKey(), $year);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $local = $this->localVersions($model, $year);

        if ($local !== []) {
            Cache::put($cacheKey, $local, now()->addHours(self::SUCCESS_TTL_HOURS));

            return $local;
        }

        $names = $this->fetchFromFirecrawl($model, $year);

        if ($names === []) {
            Cache::put($cacheKey, [], now()->addMinutes(self::ERROR_TTL_MINUTES));

            return [];
        }

        $versions = [];

        foreach ($names as $name) {
            $version = $this->firstOrCreate($model, $name, $year);
            $versions[] = ['id' => $version->getKey(), 'name' => $version->name];
        }

        Cache::put($cacheKey, $versions, now()->addHours(self::SUCCESS_TTL_HOURS));

        return $versions;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function localVersions(CarModel $model, int $year): array
    {
        return CarVersion::query()
            ->where('model_id', $model->getKey())
            ->where(function ($query) use ($year) {
                $query->where('year', (string) $year)
                    ->orWhere('year', 'like', '%'.$year.'%');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CarVersion $version) => [
                'id' => $version->getKey(),
                'name' => $version->name,
            ])
            ->all();
    }

    public function validateForModel(CarModel $model, string $versionId, int $year): ?CarVersion
    {
        $version = CarVersion::query()
            ->whereKey($versionId)
            ->where('model_id', $model->getKey())
            ->first();

        if (! $version) {
            return null;
        }

        $storedYear = trim((string) $version->year);

        if ($storedYear === '' || ! str_contains($storedYear, (string) $year)) {
            return null;
        }

        return $version;
    }

    public function firstOrCreate(CarModel $model, string $name, int $year): CarVersion
    {
        $name = trim($name);

        $existing = CarVersion::query()
            ->where('model_id', $model->getKey())
            ->where('year', (string) $year)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return CarVersion::query()->create([
            'model_id' => $model->getKey(),
            'name' => $name,
            'year' => (string) $year,
        ]);
    }

    public function cacheKey(string $modelId, int $year): string
    {
        return 'car-versions:'.$modelId.':'.$year;
    }

    public function forgetCache(string $modelId, mixed $year): void
    {
        $value = trim((string) $year);

        if ($value !== '') {
            Cache::forget($this->cacheKey($modelId, (int) $value));
        }

        if (preg_match_all('/\b(19|20)\d{2}\b/', $value, $matches)) {
            foreach (array_unique($matches[0]) as $matchedYear) {
                Cache::forget($this->cacheKey($modelId, (int) $matchedYear));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchFromFirecrawl(CarModel $model, int $year): array
    {
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '') {
            return [];
        }

        $model->loadMissing('brand');

        $brand = (string) ($model->brand?->name ?? '');
        $modelName = (string) $model->name;
        $query = trim($brand.' '.$modelName.' '.$year.' versiones equipamiento trims México');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.firecrawl.dev/v2/search', [
                    'query' => $query,
                    'limit' => 5,
                    'scrapeOptions' => [
                        'onlyMainContent' => true,
                        'formats' => [[
                            'type' => 'json',
                            'prompt' => implode(' ', [
                                'Estoy buscando las versiones/trims/equipamientos disponibles del auto '.$brand.' '.$modelName.' del año '.$year.' en México.',
                                'Extrae TODOS los nombres de versiones que encuentres en esta página.',
                                'No incluyas el nombre de la marca ni del modelo, solo el nombre de la versión/trim.',
                                'No incluyas precios, años ni kilometrajes.',
                                'Si no encuentras versiones específicas, devuelve un array vacío.',
                            ]),
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'versiones' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Lista de nombres de versiones/trims del auto',
                                    ],
                                ],
                                'required' => ['versiones'],
                            ],
                        ]],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Firecrawl versions request failed', [
                    'status' => $response->status(),
                    'model' => $modelName,
                    'year' => $year,
                ]);

                return [];
            }

            $json = $response->json();
            $results = $json['data']['web'] ?? $json['web'] ?? $json['data'] ?? [];

            if (! is_array($results)) {
                return [];
            }

            $names = [];

            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $extracted = $result['json'] ?? $result['extract'] ?? data_get($result, 'data.json');
                $versions = is_array($extracted['versiones'] ?? null)
                    ? $extracted['versiones']
                    : (is_array($extracted['versions'] ?? null) ? $extracted['versions'] : []);

                foreach ($versions as $versionName) {
                    if (! is_string($versionName)) {
                        continue;
                    }

                    $versionName = trim($versionName);

                    if ($versionName === '' || mb_strlen($versionName) > 80) {
                        continue;
                    }

                    $key = mb_strtolower($versionName);
                    $names[$key] = $versionName;
                }
            }

            $names = array_values($names);
            sort($names);

            return $names;
        } catch (\Throwable $exception) {
            Log::warning('Firecrawl versions request error', [
                'model' => $modelName,
                'year' => $year,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
