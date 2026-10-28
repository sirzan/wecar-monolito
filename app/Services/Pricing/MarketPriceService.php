<?php

namespace App\Services\Pricing;

use App\Models\CarModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketPriceService
{
    private const MIN_SANE_PRICE = 30_000;

    private const MAX_SANE_PRICE = 15_000_000;

    private const SUCCESS_TTL_HOURS = 12;

    private const EMPTY_TTL_HOURS = 1;

    private const ERROR_TTL_MINUTES = 5;

    /**
     * @return array<string, mixed>
     */
    public function suggest(CarModel $model, int $year, ?string $versionName = null, ?int $km = null): array
    {
        $key = $this->cacheKey($model, $year, $versionName, $km);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return [...$cached, 'cached' => true];
        }

        $lock = null;
        $acquired = false;

        try {
            $lock = Cache::lock('market-price:'.$key, 45);
            $acquired = $lock->get();

            if (! $acquired) {
                usleep(250_000);
                $cached = Cache::get($key);

                if (is_array($cached)) {
                    return [...$cached, 'cached' => true];
                }
            }
        } catch (\Throwable) {
            $acquired = false;
        }

        try {
            $result = $this->fetch($model, $year, $versionName, $km);
            $ttl = $this->ttlFor($result);
            Cache::put($key, $result, $ttl);

            return [...$result, 'cached' => false];
        } finally {
            if ($lock && $acquired) {
                try {
                    $lock->release();
                } catch (\Throwable) {
                    // El lock pudo expirar.
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(CarModel $model, int $year, ?string $versionName, ?int $km): array
    {
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '') {
            return $this->empty('FIRECRAWL_API_KEY no configurada');
        }

        $model->loadMissing('brand');

        $brand = (string) ($model->brand?->name ?? '');
        $modelName = (string) $model->name;
        $version = trim((string) $versionName);
        $versionStr = $version !== '' ? ' '.$version : '';
        $kmBucket = $this->bucketKm($km);
        $kmStr = $kmBucket > 0 ? ' '.number_format($kmBucket, 0, '.', ',').' km' : '';
        $query = trim($brand.' '.$modelName.$versionStr.' '.$year.$kmStr.' precio México pesos');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.firecrawl.dev/v2/search', [
                    'query' => $query,
                    'limit' => 3,
                    'scrapeOptions' => [
                        'onlyMainContent' => true,
                        'formats' => [
                            [
                                'type' => 'json',
                                'prompt' => implode(' ', array_filter([
                                    'Estoy buscando el precio de mercado de un auto '.$brand.' '.$modelName.$versionStr.' del año '.$year.' en México'.($kmBucket > 0 ? ' con aproximadamente '.number_format($kmBucket, 0, '.', ',').' kilómetros' : '').'.',
                                    'Extrae los precios en pesos mexicanos (MXN) como números enteros sin separadores de miles ni símbolos.',
                                    'precio_minimo: el precio más bajo encontrado para este auto.',
                                    'precio_sugerido: el precio promedio o de referencia más común.',
                                    'precio_maximo: el precio más alto encontrado para este auto.',
                                    $kmBucket > 0 ? 'Considera que el kilometraje aproximado es '.number_format($kmBucket, 0, '.', ',').' km. Autos con menos km suelen valer más.' : '',
                                    'Si solo encuentras un precio, úsalo como precio_sugerido y deja los demás en null.',
                                    'Si no encuentras precios para este auto específico, devuelve null en todos los campos.',
                                ])),
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'precio_minimo' => ['type' => ['number', 'null']],
                                        'precio_sugerido' => ['type' => ['number', 'null']],
                                        'precio_maximo' => ['type' => ['number', 'null']],
                                    ],
                                    'required' => ['precio_minimo', 'precio_sugerido', 'precio_maximo'],
                                ],
                            ],
                            ['type' => 'markdown'],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Firecrawl market price request failed', [
                    'status' => $response->status(),
                    'model' => $modelName,
                    'year' => $year,
                ]);

                return $this->empty('No se pudo consultar el mercado.');
            }

            $json = $response->json();
            $results = $json['data']['web'] ?? $json['web'] ?? $json['data'] ?? [];

            if (! is_array($results)) {
                return $this->empty('Sin coincidencias de precio');
            }

            return $this->aggregate($results);
        } catch (\Throwable $exception) {
            Log::warning('Firecrawl market price request error', [
                'model' => $modelName,
                'year' => $year,
                'error' => $exception->getMessage(),
            ]);

            return $this->empty('Error al consultar precios');
        }
    }

    /**
     * @param  array<int, mixed>  $results
     * @return array<string, mixed>
     */
    private function aggregate(array $results): array
    {
        $sources = [];
        $sourceCount = 0;

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $extracted = $result['json'] ?? $result['extract'] ?? data_get($result, 'data.json');
            $sourceUrl = (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? '');

            if (is_array($extracted)) {
                $min = $this->priceOrNull($extracted['precio_minimo'] ?? null);
                $suggested = $this->priceOrNull($extracted['precio_sugerido'] ?? null);
                $max = $this->priceOrNull($extracted['precio_maximo'] ?? null);

                if ($min || $suggested || $max) {
                    $sources[] = compact('min', 'suggested', 'max') + ['source' => $sourceUrl ?: 'web'];
                    $sourceCount++;
                }
            }
        }

        if ($sources === []) {
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $markdown = (string) ($result['markdown'] ?? $result['content'] ?? '');
                $sourceUrl = (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? '');

                foreach ($this->pricesFromMarkdown($markdown) as $price) {
                    $sources[] = ['min' => null, 'suggested' => $price, 'max' => null, 'source' => $sourceUrl ?: 'markdown'];
                    $sourceCount++;
                }
            }
        }

        if ($sources === []) {
            return $this->empty('Sin coincidencias de precio');
        }

        $values = [];

        foreach ($sources as $source) {
            foreach ([$source['min'], $source['suggested'], $source['max']] as $price) {
                if ($price !== null && $price >= self::MIN_SANE_PRICE && $price <= self::MAX_SANE_PRICE) {
                    $values[] = $price;
                }
            }
        }

        $values = $this->removeOutliers($values);

        if ($values === []) {
            return $this->empty('Sin coincidencias de precio');
        }

        $representatives = [];

        foreach ($sources as $source) {
            $representative = $source['suggested'];

            if ($representative === null && $source['min'] !== null && $source['max'] !== null) {
                $representative = ($source['min'] + $source['max']) / 2;
            }

            $representative ??= $source['min'] ?? $source['max'];

            if ($representative !== null && $representative >= self::MIN_SANE_PRICE && $representative <= self::MAX_SANE_PRICE) {
                $representatives[] = $representative;
            }
        }

        $representatives = $this->removeOutliers($representatives);
        $central = count($representatives) >= 3 ? $representatives : $values;
        sort($values);
        sort($central);

        $median = $this->median($central);
        $p25 = $this->percentile($central, 25);
        $p75 = $this->percentile($central, 75);
        $spread = $median > 0 ? ($p75 - $p25) / $median : 1;

        return [
            'samples' => count($values),
            'source_count' => $sourceCount,
            'min' => (int) round($values[0]),
            'p25' => (int) round($p25),
            'suggested' => (int) round($median),
            'p75' => (int) round($p75),
            'max' => (int) round($values[count($values) - 1]),
            'confidence' => $this->confidence($sourceCount, $spread),
            'source' => $sources[0]['source'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function ttlFor(array $result): \DateTimeInterface
    {
        if (($result['samples'] ?? 0) > 0) {
            return now()->addHours(self::SUCCESS_TTL_HOURS);
        }

        return isset($result['error']) && $result['error'] !== 'Sin coincidencias de precio'
            ? now()->addMinutes(self::ERROR_TTL_MINUTES)
            : now()->addHours(self::EMPTY_TTL_HOURS);
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $error): array
    {
        return [
            'samples' => 0,
            'source_count' => 0,
            'min' => null,
            'p25' => null,
            'suggested' => null,
            'p75' => null,
            'max' => null,
            'confidence' => 'none',
            'source' => null,
            'error' => $error,
        ];
    }

    private function cacheKey(CarModel $model, int $year, ?string $versionName, ?int $km): string
    {
        $model->loadMissing('brand');

        $key = implode('|', [
            Str::lower(Str::ascii((string) ($model->brand?->name ?? ''))),
            Str::lower(Str::ascii((string) $model->name)),
            Str::lower(Str::ascii(trim((string) $versionName))),
            $year,
            $this->bucketKm($km),
        ]);

        return 'market-price:'.md5($key);
    }

    private function bucketKm(?int $km): int
    {
        if (! $km || $km <= 0) {
            return 0;
        }

        return (int) (round($km / 10_000) * 10_000);
    }

    private function priceOrNull(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $number = $value;
        } elseif (is_string($value)) {
            $lower = mb_strtolower($value);
            $multiplier = str_contains($lower, 'millon') ? 1_000_000 : 1;
            $digits = preg_replace('/\D+/', '', $value);
            $number = $digits !== '' ? ((int) $digits) * $multiplier : 0;
        } else {
            return null;
        }

        return $number > 0 ? (int) round($number) : null;
    }

    /**
     * @return array<int, int>
     */
    private function pricesFromMarkdown(string $markdown): array
    {
        if ($markdown === '') {
            return [];
        }

        preg_match_all('/\$\s?(\d{1,3}(?:[.,]\d{3})+|\d{4,9})/', $markdown, $matches);
        $prices = [];

        foreach ($matches[1] ?? [] as $value) {
            $price = $this->priceOrNull($value);

            if ($price !== null && $price >= self::MIN_SANE_PRICE && $price <= self::MAX_SANE_PRICE) {
                $prices[] = $price;
            }
        }

        return $prices;
    }

    /**
     * @param  array<int, int|float>  $values
     * @return array<int, int|float>
     */
    private function removeOutliers(array $values): array
    {
        $values = array_values(array_filter($values, fn ($price) => $price >= self::MIN_SANE_PRICE && $price <= self::MAX_SANE_PRICE));
        sort($values);

        if (count($values) < 4) {
            return $values;
        }

        $q1 = $this->percentile($values, 25);
        $q3 = $this->percentile($values, 75);
        $iqr = $q3 - $q1;
        $filtered = array_values(array_filter($values, fn ($price) => $price >= $q1 - (1.5 * $iqr) && $price <= $q3 + (1.5 * $iqr)));

        return $filtered !== [] ? $filtered : $values;
    }

    /**
     * @param  array<int, int|float>  $sorted
     */
    private function percentile(array $sorted, float $percentile): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0;
        }

        if ($count === 1) {
            return (float) $sorted[0];
        }

        $index = ($percentile / 100) * ($count - 1);
        $low = (int) floor($index);
        $high = (int) ceil($index);

        return $low === $high
            ? (float) $sorted[$low]
            : (float) ($sorted[$low] + (($sorted[$high] - $sorted[$low]) * ($index - $low)));
    }

    /**
     * @param  array<int, int|float>  $sorted
     */
    private function median(array $sorted): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? (float) (($sorted[$middle - 1] + $sorted[$middle]) / 2)
            : (float) $sorted[$middle];
    }

    private function confidence(int $sourceCount, float $spread): string
    {
        if ($sourceCount >= 3 && $spread <= 0.35) {
            return 'high';
        }

        if ($sourceCount >= 2 && $spread <= 0.6) {
            return 'medium';
        }

        return 'low';
    }
}
