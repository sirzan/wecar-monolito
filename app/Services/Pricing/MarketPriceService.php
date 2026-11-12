<?php

namespace App\Services\Pricing;

use App\Models\CarModel;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketPriceService
{
    private const MIN_SANE_PRICE = 30_000;

    private const MAX_SANE_PRICE = 15_000_000;

    private const SUCCESS_TTL_HOURS = 24;

    private const EMPTY_TTL_MINUTES = 10;

    private const ERROR_TTL_MINUTES = 1;

    private const EXPECTED_KM_PER_YEAR = 15_000;

    /**
     * @var array<int, string>
     */
    private const LIBRO_AZUL_DOMAINS = [
        'libroazul.com',
        'libroazul.app',
        'guiaebc.com',
    ];

    /**
     * @var array<int, string>
     */
    private const MEXICAN_MARKET_DOMAINS = [
        'autocosmos.com.mx',
        'kavak.com',
        'autos.mercadolibre.com.mx',
        'seminuevos.com',
        'soloautos.mx',
    ];

    /**
     * @return array<string, mixed>
     */
    public function suggest(
        CarModel $model,
        int $year,
        ?string $versionName = null,
        ?int $km = null,
        bool $forceRefresh = false,
    ): array {
        $key = $this->cacheKey($model, $year, $versionName);

        if (! $forceRefresh) {
            $cached = Cache::get($key);

            if (is_array($cached)) {
                return [
                    ...$this->applyMileageAdjustment($cached, $km, $year),
                    'cached' => true,
                ];
            }
        }

        $lock = null;
        $acquired = false;

        try {
            $lock = Cache::lock('market-price:lock:'.$key, 55);
            $acquired = $lock->get();

            if (! $acquired && ! $forceRefresh) {
                usleep(300_000);
                $cached = Cache::get($key);

                if (is_array($cached)) {
                    return [
                        ...$this->applyMileageAdjustment($cached, $km, $year),
                        'cached' => true,
                    ];
                }
            }
        } catch (\Throwable) {
            $acquired = false;
        }

        try {
            $result = $this->fetch($model, $year, $versionName);
            Cache::put($key, $result, $this->ttlFor($result));

            return [
                ...$this->applyMileageAdjustment($result, $km, $year),
                'cached' => false,
            ];
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
    private function fetch(CarModel $model, int $year, ?string $versionName): array
    {
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '') {
            return $this->empty('FIRECRAWL_API_KEY no configurada');
        }

        $model->loadMissing('brand');

        $brand = trim((string) ($model->brand?->name ?? ''));
        $modelName = trim((string) $model->name);
        $version = trim((string) $versionName);

        $libroAzulQuery = trim(implode(' ', array_filter([
            $brand,
            $modelName,
            $version,
            (string) $year,
            'Libro Azul precio compra venta pesos',
        ])));

        $marketMxQuery = trim(implode(' ', array_filter([
            $brand,
            $modelName,
            $version,
            (string) $year,
            'seminuevo usado precio México MXN',
        ])));

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('libro_azul')
                    ->withToken($apiKey)
                    ->connectTimeout(8)
                    ->timeout(45)
                    ->post('https://api.firecrawl.dev/v2/search', $this->firecrawlPayload(
                        $libroAzulQuery,
                        self::LIBRO_AZUL_DOMAINS,
                        5,
                        $brand,
                        $modelName,
                        $version,
                        $year,
                    )),
                $pool->as('market_mx')
                    ->withToken($apiKey)
                    ->connectTimeout(8)
                    ->timeout(45)
                    ->post('https://api.firecrawl.dev/v2/search', $this->firecrawlPayload(
                        $marketMxQuery,
                        self::MEXICAN_MARKET_DOMAINS,
                        5,
                        $brand,
                        $modelName,
                        $version,
                        $year,
                    )),
            ]);

            $libroAzulResults = $this->extractSearchResults($responses['libro_azul'] ?? null, 'libro_azul', $modelName, $year);
            $marketMxResults = $this->extractSearchResults($responses['market_mx'] ?? null, 'market_mx', $modelName, $year);

            if ($libroAzulResults === null && $marketMxResults === null) {
                return $this->empty('No se pudo consultar el mercado en este momento.');
            }

            return $this->aggregate(
                $libroAzulResults ?? [],
                $marketMxResults ?? [],
                $brand,
                $modelName,
                $version,
                $year,
            );
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
     * @param  array<int, string>  $domains
     * @return array<string, mixed>
     */
    private function firecrawlPayload(
        string $query,
        array $domains,
        int $limit,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): array {
        $carLabel = trim(implode(' ', array_filter([$brand, $modelName, $version, (string) $year])));

        return [
            'query' => $query,
            'limit' => $limit,
            'country' => 'MX',
            'location' => 'Mexico',
            'includeDomains' => $domains,
            'scrapeOptions' => [
                'onlyMainContent' => true,
                'formats' => [
                    [
                        'type' => 'json',
                        'prompt' => implode(' ', [
                            "Extrae únicamente referencias de precio EN PESOS MEXICANOS (MXN) para el vehículo USADO/SEMINUEVO: {$carLabel} en México.",
                            'Reglas estrictas:',
                            '1) Solo reporta moneda "MXN" si el precio está claramente en pesos mexicanos (MXN, MX$, pesos o portal mexicano). Si el precio está en dólares (USD/US$), pesos colombianos (COP), chilenos (CLP) o argentinos (ARS), marca moneda como "USD" u "OTRA".',
                            '2) Indica tipo_registro: "libro_azul" si proviene de Libro Azul / Guía EBC, "guia_precios" si es tabulador de precios usados, "seminuevo_venta" si es anuncio de seminuevo en venta, "auto_nuevo" si es vehículo 0 km/nuevo, o "financiamiento" si es enganche o mensualidad.',
                            '3) Si es Libro Azul o tabulador con precio de compra (toma) y precio de venta (público), extrae ambos (precio_compra y precio_venta) como números enteros MXN sin decimales ni símbolos.',
                            '4) Incluye anio (entero exacto del auto listado) y version exacta si aparece.',
                            '5) Nunca inventes precios. Si no hay precios reales de este modelo y año, devuelve valuaciones: [].',
                        ]),
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'valuaciones' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'marca' => ['type' => ['string', 'null']],
                                            'modelo' => ['type' => ['string', 'null']],
                                            'anio' => ['type' => ['integer', 'null']],
                                            'version' => ['type' => ['string', 'null']],
                                            'moneda' => ['type' => 'string', 'enum' => ['MXN', 'USD', 'OTRA']],
                                            'tipo_registro' => ['type' => 'string', 'enum' => ['libro_azul', 'guia_precios', 'seminuevo_venta', 'auto_nuevo', 'financiamiento']],
                                            'precio_compra' => ['type' => ['number', 'null']],
                                            'precio_venta' => ['type' => ['number', 'null']],
                                            'precio_sugerido' => ['type' => ['number', 'null']],
                                        ],
                                        'required' => ['moneda', 'tipo_registro'],
                                    ],
                                ],
                            ],
                            'required' => ['valuaciones'],
                        ],
                    ],
                    ['type' => 'markdown'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, mixed>|null
     */
    private function extractSearchResults(mixed $response, string $channel, string $modelName, int $year): ?array
    {
        if (! $response instanceof Response) {
            Log::warning('Firecrawl market price pool exception', [
                'channel' => $channel,
                'model' => $modelName,
                'year' => $year,
                'error' => $response instanceof \Throwable ? $response->getMessage() : 'Respuesta inválida',
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Firecrawl market price request failed', [
                'channel' => $channel,
                'status' => $response->status(),
                'model' => $modelName,
                'year' => $year,
            ]);

            return null;
        }

        $json = $response->json();
        $results = $json['data']['web'] ?? $json['web'] ?? $json['data'] ?? [];

        return is_array($results) ? $results : [];
    }

    /**
     * @param  array<int, mixed>  $libroAzulResults
     * @param  array<int, mixed>  $marketMxResults
     * @return array<string, mixed>
     */
    private function aggregate(
        array $libroAzulResults,
        array $marketMxResults,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): array {
        $libroAzulItems = $this->collectItems($libroAzulResults, 'libro_azul', $brand, $modelName, $version, $year);
        $marketMxItems = $this->collectItems($marketMxResults, 'market_mx', $brand, $modelName, $version, $year);

        $libroAzulFallback = $libroAzulItems === []
            ? $this->collectFromMarkdownFallback($libroAzulResults, 'libro_azul', $brand, $modelName, $version, $year)
            : [];
        $marketMxFallback = $marketMxItems === []
            ? $this->collectFromMarkdownFallback($marketMxResults, 'market_mx', $brand, $modelName, $version, $year)
            : [];

        $libroAzulAll = [...$libroAzulItems, ...$libroAzulFallback];
        $marketMxAll = [...$marketMxItems, ...$marketMxFallback];

        // Si alguna referencia de market_mx viene etiquetada como libro_azul (por ejemplo en blog/portal MX), la promovemos.
        foreach ($marketMxAll as $item) {
            if ($item['record_type'] === 'libro_azul') {
                $libroAzulAll[] = $item;
            }
        }

        if ($libroAzulAll !== []) {
            return $this->summarize($libroAzulAll, $marketMxAll, 'libro_azul', $version);
        }

        if ($marketMxAll !== []) {
            return $this->summarize($marketMxAll, [], 'market_mx', $version);
        }

        return $this->empty('Sin coincidencias de precio en MXN para este vehículo');
    }

    /**
     * @param  array<int, mixed>  $results
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(
        array $results,
        string $channel,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): array {
        $items = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $sourceUrl = (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? '');

            if (! $this->isAllowedMexicanUrl($sourceUrl)) {
                continue;
            }

            $extracted = $result['json'] ?? $result['extract'] ?? data_get($result, 'data.json');

            if (! is_array($extracted)) {
                continue;
            }

            $rawValuations = is_array($extracted['valuaciones'] ?? null)
                ? $extracted['valuaciones']
                : [$extracted];

            foreach ($rawValuations as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $normalized = $this->normalizeValuation($raw, $sourceUrl, $channel, $brand, $modelName, $version, $year);

                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalizeValuation(
        array $raw,
        string $sourceUrl,
        string $channel,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): ?array {
        $currency = strtoupper(trim((string) ($raw['moneda'] ?? 'MXN')));

        if ($currency !== 'MXN') {
            return null;
        }

        $recordType = strtolower(trim((string) ($raw['tipo_registro'] ?? ($channel === 'libro_azul' ? 'libro_azul' : 'seminuevo_venta'))));

        if (in_array($recordType, ['auto_nuevo', 'financiamiento'], true)) {
            return null;
        }

        $extractedYear = isset($raw['anio']) && is_numeric($raw['anio']) ? (int) $raw['anio'] : null;

        if ($extractedYear !== null && $extractedYear !== $year) {
            return null;
        }

        $extractedModel = trim((string) ($raw['modelo'] ?? ''));

        if ($extractedModel !== '' && ! $this->matchesModel($extractedModel, $brand, $modelName)) {
            return null;
        }

        $buy = $this->sanePrice($raw['precio_compra'] ?? null);
        $sell = $this->sanePrice($raw['precio_venta'] ?? null);
        $suggested = $this->sanePrice($raw['precio_sugerido'] ?? null);

        // Soporte retrocompatible con esquema simple previo si llegara a presentarse.
        $legacyMin = $this->sanePrice($raw['precio_minimo'] ?? null);
        $legacyMax = $this->sanePrice($raw['precio_maximo'] ?? null);

        if ($buy === null && $legacyMin !== null && $recordType === 'libro_azul') {
            $buy = $legacyMin;
        }

        if ($sell === null && $legacyMax !== null && $recordType === 'libro_azul') {
            $sell = $legacyMax;
        }

        if ($buy !== null && $sell !== null && $buy > $sell) {
            $buy = null;
        }

        $representative = $suggested ?? $sell ?? $buy ?? $legacyMin ?? $legacyMax;

        if ($representative === null) {
            return null;
        }

        $extractedVersion = trim((string) ($raw['version'] ?? ''));
        $versionMatched = $version !== '' && $extractedVersion !== '' && $this->matchesVersion($extractedVersion, $version);

        return [
            'buy' => $buy,
            'sell' => $sell,
            'suggested' => $representative,
            'min' => $legacyMin ?? $buy ?? $representative,
            'max' => $legacyMax ?? $sell ?? $representative,
            'record_type' => $channel === 'libro_azul' ? 'libro_azul' : $recordType,
            'version_matched' => $versionMatched,
            'source' => $sourceUrl !== '' ? $sourceUrl : $channel,
        ];
    }

    /**
     * @param  array<int, mixed>  $results
     * @return array<int, array<string, mixed>>
     */
    private function collectFromMarkdownFallback(
        array $results,
        string $channel,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): array {
        $pages = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $sourceUrl = (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? '');
            $markdown = trim((string) ($result['markdown'] ?? $result['content'] ?? $result['description'] ?? ''));

            if ($markdown === '' || ! $this->isAllowedMexicanUrl($sourceUrl)) {
                continue;
            }

            $pages[] = [
                'url' => $sourceUrl,
                'title' => (string) ($result['title'] ?? ''),
                'content' => $markdown,
            ];
        }

        if ($pages === []) {
            return [];
        }

        $aiItems = $this->extractWithOpenAi($pages, $channel, $brand, $modelName, $version, $year);

        if ($aiItems !== []) {
            return $aiItems;
        }

        $strictItems = [];

        foreach ($pages as $page) {
            foreach ($this->pricesFromMarkdownStrict($page['content'], $modelName, $year) as $price) {
                $strictItems[] = [
                    'buy' => null,
                    'sell' => null,
                    'suggested' => $price,
                    'min' => $price,
                    'max' => $price,
                    'record_type' => $channel === 'libro_azul' ? 'libro_azul' : 'seminuevo_venta',
                    'version_matched' => $version !== '' && $this->matchesVersion($page['title'].' '.$page['content'], $version),
                    'source' => $page['url'] !== '' ? $page['url'] : $channel,
                ];
            }
        }

        return $strictItems;
    }

    /**
     * @param  array<int, array{url: string, title: string, content: string}>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function extractWithOpenAi(
        array $pages,
        string $channel,
        string $brand,
        string $modelName,
        string $version,
        int $year,
    ): array {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '') {
            return [];
        }

        $combined = '';
        $maxChars = 22_000;

        foreach ($pages as $page) {
            $chunk = "\n\n=== FUENTE: {$page['url']} ===\n"
                .($page['title'] !== '' ? "Título: {$page['title']}\n" : '')
                .mb_substr($page['content'], 0, 7_000);

            if (mb_strlen($combined.$chunk) > $maxChars) {
                break;
            }

            $combined .= $chunk;
        }

        if (trim($combined) === '') {
            return [];
        }

        $carLabel = trim(implode(' ', array_filter([$brand, $modelName, $version, (string) $year])));

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(6)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'max_tokens' => 900,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'mx_car_valuations',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'valuaciones' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'source_url' => ['type' => 'string'],
                                                'marca' => ['type' => ['string', 'null']],
                                                'modelo' => ['type' => ['string', 'null']],
                                                'anio' => ['type' => ['integer', 'null']],
                                                'version' => ['type' => ['string', 'null']],
                                                'moneda' => ['type' => 'string', 'enum' => ['MXN', 'USD', 'OTRA']],
                                                'tipo_registro' => ['type' => 'string', 'enum' => ['libro_azul', 'guia_precios', 'seminuevo_venta', 'auto_nuevo', 'financiamiento']],
                                                'precio_compra' => ['type' => ['number', 'null']],
                                                'precio_venta' => ['type' => ['number', 'null']],
                                                'precio_sugerido' => ['type' => ['number', 'null']],
                                            ],
                                            'required' => ['source_url', 'marca', 'modelo', 'anio', 'version', 'moneda', 'tipo_registro', 'precio_compra', 'precio_venta', 'precio_sugerido'],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                ],
                                'required' => ['valuaciones'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode(' ', [
                                "Eres un valuador automotriz experto en México. Extrae referencias reales en PESOS MEXICANOS (MXN) para el auto USADO: {$carLabel}.",
                                'Rechaza o marca como USD/OTRA cualquier monto en dólares, COP, CLP o ARS.',
                                'Rechaza como financiamiento cualquier enganche o mensualidad, y como auto_nuevo los precios 0 km.',
                                'Si es Libro Azul / Guía EBC, separa precio_compra y precio_venta.',
                                'No inventes datos. Si no hay coincidencias claras del modelo y año, devuelve valuaciones: [].',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => $combined,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = (string) $response->json('choices.0.message.content', '');
            $decoded = json_decode($content, true);
            $rawItems = is_array($decoded['valuaciones'] ?? null) ? $decoded['valuaciones'] : [];
            $items = [];

            foreach ($rawItems as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $url = (string) ($raw['source_url'] ?? $pages[0]['url']);
                $normalized = $this->normalizeValuation($raw, $url, $channel, $brand, $modelName, $version, $year);

                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $primaryItems
     * @param  array<int, array<string, mixed>>  $secondaryItems
     * @return array<string, mixed>
     */
    private function summarize(array $primaryItems, array $secondaryItems, string $sourceType, string $version): array
    {
        $versionMatched = array_values(array_filter($primaryItems, fn (array $item) => (bool) ($item['version_matched'] ?? false)));
        $selected = $versionMatched !== [] ? $versionMatched : $primaryItems;
        $matchLevel = $version !== '' && $versionMatched !== [] ? 'version' : 'model';

        $suggestedValues = array_values(array_filter(array_map(fn (array $item) => $item['suggested'], $selected)));
        $suggestedValues = $this->removeOutliers($suggestedValues);

        if ($suggestedValues === []) {
            return $this->empty('Sin coincidencias de precio en MXN para este vehículo');
        }

        $allValues = [];

        foreach ($selected as $item) {
            foreach ([$item['min'], $item['suggested'], $item['max']] as $price) {
                if ($price !== null && $price >= self::MIN_SANE_PRICE && $price <= self::MAX_SANE_PRICE) {
                    $allValues[] = $price;
                }
            }
        }

        // Si tenemos un respaldo de mercado MX y el primario tiene pocas muestras, usamos ambos solo para enriquecer rango (no alterar suggested si es Libro Azul).
        if ($sourceType === 'libro_azul' && count($allValues) < 3 && $secondaryItems !== []) {
            $secondaryVersion = array_values(array_filter($secondaryItems, fn (array $item) => (bool) ($item['version_matched'] ?? false)));
            $secondaryPool = $secondaryVersion !== [] ? $secondaryVersion : $secondaryItems;

            foreach ($secondaryPool as $item) {
                if ($item['suggested'] !== null) {
                    $allValues[] = $item['suggested'];
                }
            }
        }

        $allValues = $this->removeOutliers($allValues);

        if ($allValues === []) {
            $allValues = $suggestedValues;
        }

        sort($suggestedValues);
        sort($allValues);

        $median = $this->median($suggestedValues);
        $p25 = $this->percentile(count($suggestedValues) >= 3 ? $suggestedValues : $allValues, 25);
        $p75 = $this->percentile(count($suggestedValues) >= 3 ? $suggestedValues : $allValues, 75);
        $spread = $median > 0 ? ($p75 - $p25) / $median : 1;

        $buyPrices = $this->removeOutliers(array_values(array_filter(array_map(fn (array $item) => $item['buy'], $selected))));
        $sellPrices = $this->removeOutliers(array_values(array_filter(array_map(fn (array $item) => $item['sell'], $selected))));

        $buyPrice = $buyPrices !== [] ? (int) round($this->median($buyPrices)) : null;
        $sellPrice = $sellPrices !== [] ? (int) round($this->median($sellPrices)) : null;

        if ($buyPrice !== null && $sellPrice !== null && $buyPrice > $sellPrice) {
            $buyPrice = null;
        }

        $uniqueSources = array_values(array_unique(array_filter(array_map(fn (array $item) => $this->domainFromUrl((string) $item['source']), $selected))));
        $sourceCount = max(1, count($uniqueSources));

        return [
            'samples' => count($suggestedValues),
            'source_count' => $sourceCount,
            'currency' => 'MXN',
            'min' => (int) round($allValues[0]),
            'p25' => (int) round($p25),
            'suggested' => (int) round($median),
            'p75' => (int) round($p75),
            'max' => (int) round($allValues[count($allValues) - 1]),
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'confidence' => $this->confidence($sourceCount, count($suggestedValues), $spread, $sourceType, $matchLevel),
            'source' => $selected[0]['source'] ?? null,
            'source_type' => $sourceType,
            'source_label' => $sourceType === 'libro_azul' ? 'Libro Azul (Guía EBC)' : 'Guía de mercado México (MXN)',
            'match_level' => $matchLevel,
            'sources' => $uniqueSources,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyMileageAdjustment(array $result, ?int $km, int $year): array
    {
        if (($result['suggested'] ?? null) === null) {
            return $result;
        }

        $expectedKm = max(1, (int) date('Y') - $year) * self::EXPECTED_KM_PER_YEAR;

        if ($km === null || $km < 0) {
            return [
                ...$result,
                'km' => $km,
                'expected_km' => $expectedKm,
                'km_adjusted' => false,
            ];
        }

        $differenceInTenThousands = ($expectedKm - $km) / 10_000;
        $factor = 1 + max(-0.18, min(0.15, $differenceInTenThousands * 0.02));

        $adjust = fn (?int $value): ?int => $value !== null
            ? max(self::MIN_SANE_PRICE, min(self::MAX_SANE_PRICE, (int) (round(($value * $factor) / 100) * 100)))
            : null;

        return [
            ...$result,
            'min' => $adjust($result['min'] ?? null),
            'p25' => $adjust($result['p25'] ?? null),
            'suggested' => $adjust($result['suggested'] ?? null),
            'p75' => $adjust($result['p75'] ?? null),
            'max' => $adjust($result['max'] ?? null),
            'buy_price' => $adjust($result['buy_price'] ?? null),
            'sell_price' => $adjust($result['sell_price'] ?? null),
            'km' => $km,
            'expected_km' => $expectedKm,
            'km_adjusted' => abs($factor - 1) >= 0.005,
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

        return isset($result['error']) && ! str_starts_with((string) $result['error'], 'Sin coincidencias')
            ? now()->addMinutes(self::ERROR_TTL_MINUTES)
            : now()->addMinutes(self::EMPTY_TTL_MINUTES);
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $error): array
    {
        return [
            'samples' => 0,
            'source_count' => 0,
            'currency' => 'MXN',
            'min' => null,
            'p25' => null,
            'suggested' => null,
            'p75' => null,
            'max' => null,
            'buy_price' => null,
            'sell_price' => null,
            'confidence' => 'none',
            'source' => null,
            'source_type' => 'none',
            'source_label' => 'Sin referencia disponible',
            'match_level' => 'none',
            'sources' => [],
            'km_adjusted' => false,
            'error' => $error,
        ];
    }

    private function cacheKey(CarModel $model, int $year, ?string $versionName): string
    {
        $model->loadMissing('brand');

        $key = implode('|', [
            Str::lower(Str::ascii((string) ($model->brand?->name ?? ''))),
            Str::lower(Str::ascii((string) $model->name)),
            Str::lower(Str::ascii(trim((string) $versionName))),
            $year,
        ]);

        return 'market-price:v2:'.md5($key);
    }

    private function sanePrice(mixed $value): ?int
    {
        $price = $this->priceOrNull($value);

        if ($price === null || $price < self::MIN_SANE_PRICE || $price > self::MAX_SANE_PRICE) {
            return null;
        }

        return $price;
    }

    private function priceOrNull(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } elseif (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            if ($normalized === '') {
                return null;
            }

            if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(?:millones?|mdp|m)\b/u', $normalized, $matches)) {
                $number = (float) str_replace(',', '.', $matches[1]) * 1_000_000;
            } elseif (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(?:mil|k)\b/u', $normalized, $matches)) {
                $number = (float) str_replace(',', '.', $matches[1]) * 1_000;
            } elseif (preg_match('/\$\s*(\d[\d.,]*)/', $normalized, $token) || preg_match('/\d[\d.,]*/', $normalized, $token)) {
                $cleaned = rtrim($token[1] ?? $token[0], '.,');

                if (preg_match('/^([0-9.,]+)[.,](\d{1,2})$/', $cleaned, $parts)) {
                    $integerPart = preg_replace('/\D+/', '', $parts[1]);
                    $number = $integerPart !== '' ? (float) ($integerPart.'.'.$parts[2]) : 0.0;
                } else {
                    $digits = preg_replace('/\D+/', '', $cleaned);
                    $number = $digits !== '' ? (float) $digits : 0.0;
                }
            } else {
                return null;
            }
        } else {
            return null;
        }

        return $number > 0 ? (int) round($number) : null;
    }

    /**
     * @return array<int, int>
     */
    private function pricesFromMarkdownStrict(string $markdown, string $modelName, int $year): array
    {
        if ($markdown === '') {
            return [];
        }

        $normalizedDoc = Str::lower(Str::ascii($markdown));
        $docCompact = preg_replace('/[^a-z0-9]/', '', $normalizedDoc) ?? '';
        $modelCompact = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($modelName))) ?? '';

        if (! str_contains($normalizedDoc, (string) $year) || ($modelCompact !== '' && ! str_contains($docCompact, $modelCompact))) {
            return [];
        }

        $lines = preg_split('/\R+/', $markdown) ?: [];
        $prices = [];

        foreach ($lines as $line) {
            $lowerLine = Str::lower(Str::ascii($line));

            if (preg_match('/\b(usd|us\$|dolar|dolares|cop|clp|ars|enganche|mensual|mensualidad|quincen|semanal|bono|0\s*km|nuevo)\b/', $lowerLine)) {
                continue;
            }

            if (! preg_match_all('/(?:\$\s*|mxn\s*)(\d{1,3}(?:[.,]\d{3})+(?:[.,]\d{1,2})?|\d{5,8}(?:[.,]\d{1,2})?)/i', $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $raw) {
                $price = $this->sanePrice($raw);

                if ($price !== null) {
                    $prices[] = $price;
                }
            }
        }

        return $prices;
    }

    private function isAllowedMexicanUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        $lower = Str::lower($url);
        $host = parse_url($lower, PHP_URL_HOST) ?: '';

        if ($host !== '' && preg_match('/\.(co|cl|ar|pe|br|ec|uy|ve|es)$/', $host)) {
            return false;
        }

        if (str_contains($host, 'kavak.com')) {
            return str_contains($lower, 'kavak.com/mx');
        }

        if (str_contains($host, 'mercadolibre.com') && ! str_contains($host, 'mercadolibre.com.mx')) {
            return false;
        }

        return true;
    }

    private function matchesModel(string $extractedModel, string $brand, string $targetModel): bool
    {
        $haystack = Str::lower(Str::ascii(trim($extractedModel)));
        $target = Str::lower(Str::ascii(trim($targetModel)));
        $brandLower = Str::lower(Str::ascii(trim($brand)));

        if ($target === '') {
            return true;
        }

        $haystackCompact = preg_replace('/[^a-z0-9]/', '', str_replace($brandLower, '', $haystack)) ?? '';
        $targetCompact = preg_replace('/[^a-z0-9]/', '', $target) ?? '';

        if ($targetCompact === '' || $haystackCompact === '') {
            return str_contains($haystack, $target) || str_contains($target, $haystack);
        }

        return str_contains($haystackCompact, $targetCompact) || str_contains($targetCompact, $haystackCompact);
    }

    private function matchesVersion(string $candidate, string $targetVersion): bool
    {
        $candidateTrimTokens = $this->versionTokens($candidate);
        $targetTrimTokens = $this->versionTokens($targetVersion);

        if ($targetTrimTokens !== [] && $candidateTrimTokens !== []) {
            $matches = count(array_intersect($targetTrimTokens, $candidateTrimTokens));

            return $matches >= count($targetTrimTokens);
        }

        $candidateTokens = $this->tokens($candidate);
        $targetTokens = $this->tokens($targetVersion);

        if ($targetTokens === [] || $candidateTokens === []) {
            return false;
        }

        $matches = count(array_intersect($targetTokens, $candidateTokens));

        return $matches >= max(1, (int) ceil(count($targetTokens) / 2));
    }

    /**
     * @return array<int, string>
     */
    private function versionTokens(string $value): array
    {
        $noise = [
            'de', 'con', 'la', 'el', 'y', 'en', 'para', 'auto', 'sedan', 'hatchback', 'suv',
            'cvt', 'aut', 'automatica', 'automatico', 'std', 'manual', 'ta', 'tm', 'at', 'mt',
            '4p', '5p', '2p', 'l4', 'v6', 'v8', '2wd', '4wd', 'awd', 'fwd', 'rwd',
        ];

        return array_values(array_filter(
            $this->tokens($value),
            fn (string $part) => ! in_array($part, $noise, true),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        $ascii = Str::lower(Str::ascii($value));
        $parts = preg_split('/[^a-z0-9]+/', $ascii) ?: [];
        $ignored = ['de', 'con', 'la', 'el', 'y', 'en', 'para', 'auto', 'sedan', 'hatchback', 'suv'];

        return array_values(array_filter(
            $parts,
            fn (string $part) => $part !== '' && ! in_array($part, $ignored, true),
        ));
    }

    private function domainFromUrl(string $url): string
    {
        if (! str_contains($url, '://')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : $url;
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

    private function confidence(
        int $sourceCount,
        int $sampleCount,
        float $spread,
        string $sourceType,
        string $matchLevel,
    ): string {
        if ($sourceType === 'libro_azul' && $matchLevel === 'version' && $spread <= 0.35) {
            return 'high';
        }

        if ($sourceCount >= 3 && $sampleCount >= 3 && $spread <= 0.35) {
            return 'high';
        }

        if (($sourceCount >= 2 || $sourceType === 'libro_azul') && $spread <= 0.6) {
            return 'medium';
        }

        return 'low';
    }
}
