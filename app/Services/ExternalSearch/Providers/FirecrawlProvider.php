<?php

namespace App\Services\ExternalSearch\Providers;

use App\Models\ExternalSearchDebug;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use App\Services\ExternalSearch\OpenAiStructurer;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirecrawlProvider
{
    private const MAX_PER_PLATFORM = 12;

    private const AI_COMPLEMENT_THRESHOLD = 5;

    private const DETAIL_HYDRATE_THRESHOLD = 15;

    private const MAX_DETAIL_SCRAPES = 6;

    private const TIME_BUDGET_SECONDS = 26.0;

    public function __construct(
        private readonly ExternalCarNormalizer $normalizer,
        private readonly OpenAiStructurer $structurer,
    ) {}

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $platform, array $specs = []): array
    {
        $platformId = (string) ($platform['id'] ?? 'firecrawl');
        $batch = $this->searchBatch($query, [$platform], $specs);

        return $batch[$platformId] ?? [];
    }

    /**
     * Consulta en paralelo todas las plataformas Firecrawl y combina extracción directa + Markdown + OpenAI + hidratación acotada.
     *
     * @param  array<int, array<string, mixed>>  $platforms
     * @param  array<string, mixed>  $specs
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function searchBatch(string $query, array $platforms, array $specs = []): array
    {
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '' || $platforms === []) {
            return [];
        }

        $startedAt = microtime(true);
        $connectTimeout = (int) config('external_search.connect_timeout', 5);
        $timeout = max(12, (int) config('external_search.timeout', 26));

        $responses = $this->dispatchSearchBatch($apiKey, $query, $platforms, $connectTimeout, $timeout, false, $specs);

        $failedPlatforms = [];

        foreach ($platforms as $platform) {
            $platformId = (string) $platform['id'];
            $response = $responses[$platformId] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $failedPlatforms[$platformId] = $platform;
            }
        }

        // Reintento ligero en paralelo (nunca secuencial) solo si todavía queda presupuesto de tiempo.
        if ($failedPlatforms !== [] && (microtime(true) - $startedAt) < 15.0) {
            $retryResponses = $this->dispatchSearchBatch(
                $apiKey,
                $query,
                array_values($failedPlatforms),
                $connectTimeout,
                12,
                true,
                $specs,
            );

            foreach ($retryResponses as $platformId => $retryResponse) {
                if ($retryResponse instanceof Response && $retryResponse->successful()) {
                    $responses[$platformId] = $retryResponse;
                }
            }
        }

        $carsByPlatform = [];
        $pagesByPlatform = [];
        $platformsById = [];
        $pendingAiTasks = [];

        foreach ($platforms as $platform) {
            $platformId = (string) $platform['id'];
            $platformsById[$platformId] = $platform;
            $carsByPlatform[$platformId] = [];
            $pagesByPlatform[$platformId] = [];

            $response = $responses[$platformId] ?? null;

            if (! $response instanceof Response) {
                report(new \RuntimeException('Firecrawl sin respuesta válida para '.$platform['name']));

                Log::warning('Firecrawl platform failed', [
                    'platform' => $platformId,
                    'query' => $query,
                    'reason' => 'no_response',
                ]);

                continue;
            }

            if (! $response->successful()) {
                report(new \RuntimeException('Firecrawl '.$response->status().' para '.$platform['name']));

                Log::warning('Firecrawl platform failed', [
                    'platform' => $platformId,
                    'query' => $query,
                    'status' => $response->status(),
                ]);

                continue;
            }

            $json = $response->json();

            if (! is_array($json)) {
                continue;
            }

            $this->persistDebug($platformId, $query, $json);

            $pageResults = $this->pageResults($json);

            if ($pageResults === []) {
                continue;
            }

            $direct = $this->extractDirectCars($pageResults, $platform, $query, $specs);
            $pages = $this->extractPages($pageResults);

            $merged = $direct;

            // Extraemos primero desde Markdown/links (0ms de red) antes de gastar latencia en OpenAI.
            if ($pages !== []) {
                foreach ($this->normalizer->fallbackFromPages($pages, $platform, $query, $specs) as $car) {
                    if ($this->normalizer->isGroundedInPages($car, $pages)) {
                        $merged[$car['id']] = $merged[$car['id']] ?? $car;
                    }
                }
            }

            $carsByPlatform[$platformId] = $merged;
            $pagesByPlatform[$platformId] = $pages;

            if (count($merged) < self::AI_COMPLEMENT_THRESHOLD && $pages !== []) {
                $pendingAiTasks[$platformId] = [
                    'pages' => $pages,
                    'platform' => $platform,
                ];
            }
        }

        // Complementa en paralelo con OpenAI únicamente las plataformas con pocos resultados tras extracción local.
        $aiResults = $pendingAiTasks !== []
            ? $this->structurer->structureBatch($pendingAiTasks, $query, $specs)
            : [];

        foreach ($platformsById as $platformId => $platform) {
            $merged = $carsByPlatform[$platformId] ?? [];

            foreach ($aiResults[$platformId] ?? [] as $car) {
                $merged[$car['id']] = $merged[$car['id']] ?? $car;
            }

            $pages = $pagesByPlatform[$platformId] ?? [];

            if ($pages !== []) {
                $merged = array_filter(
                    $merged,
                    fn (array $car): bool => $this->normalizer->isGroundedInPages($car, $pages),
                );
            }

            $carsByPlatform[$platformId] = array_slice(
                $this->normalizer->rankBySpecs(array_values($merged), $specs),
                0,
                self::MAX_PER_PLATFORM,
            );

            Log::info('Firecrawl platform completed', [
                'platform' => $platformId,
                'query' => $query,
                'results' => count($carsByPlatform[$platformId]),
            ]);
        }

        $totalFound = array_sum(array_map('count', $carsByPlatform));

        if ($totalFound < self::DETAIL_HYDRATE_THRESHOLD && (microtime(true) - $startedAt) < (self::TIME_BUDGET_SECONDS - 4.0)) {
            $carsByPlatform = $this->hydrateDetailLinks(
                $carsByPlatform,
                $pagesByPlatform,
                $platformsById,
                $query,
                $apiKey,
                $connectTimeout,
                $specs,
                $startedAt,
            );
        }

        return $carsByPlatform;
    }

    /**
     * Lanza en paralelo las búsquedas de Firecrawl y espera cada promesa por separado.
     *
     * @param  array<int, array<string, mixed>>  $platforms
     * @param  array<string, mixed>  $specs
     * @return array<string, Response|null>
     */
    private function dispatchSearchBatch(
        string $apiKey,
        string $query,
        array $platforms,
        int $connectTimeout,
        int $timeout,
        bool $light,
        array $specs,
    ): array {
        $pending = [];
        $responses = [];

        foreach ($platforms as $platform) {
            $platformId = (string) $platform['id'];

            try {
                $pending[$platformId] = Http::withToken($apiKey)
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->async()
                    ->post('https://api.firecrawl.dev/v2/search', $this->buildPayload($query, $platform, $light, $specs));
            } catch (\Throwable $exception) {
                report($exception);
                $pending[$platformId] = null;
            }
        }

        foreach ($pending as $platformId => $promise) {
            if ($promise === null) {
                $responses[$platformId] = null;

                continue;
            }

            try {
                $result = $promise->wait();

                if ($result instanceof \Throwable) {
                    report($result);
                    $responses[$platformId] = null;
                } else {
                    $responses[$platformId] = $result instanceof Response ? $result : null;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $responses[$platformId] = null;
            }
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<string, mixed>
     */
    private function buildPayload(string $query, array $platform, bool $light = false, array $specs = []): array
    {
        $domains = is_array($platform['domains'] ?? null) && $platform['domains'] !== []
            ? array_values(array_filter(array_map('strval', $platform['domains'])))
            : [(string) $platform['domain']];
        $platformId = (string) ($platform['id'] ?? '');

        return [
            'query' => $this->buildSearchQuery($query, $platformId, $specs),
            'limit' => $light ? 4 : 6,
            'country' => 'MX',
            'location' => 'Mexico',
            'ignoreInvalidURLs' => true,
            'timeout' => $light ? 12000 : 22000,
            'includeDomains' => $domains,
            'scrapeOptions' => [
                'onlyMainContent' => true,
                'formats' => $light ? ['markdown', 'images'] : ['markdown', 'links', 'images'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    private function buildSearchQuery(string $query, string $platformId = '', array $specs = []): string
    {
        $structuredParts = array_values(array_filter([
            is_string($specs['brand'] ?? null) ? trim($specs['brand']) : null,
            is_string($specs['model'] ?? null) ? trim($specs['model']) : null,
            is_numeric($specs['year'] ?? null) ? (string) $specs['year'] : null,
        ]));

        // Evitamos meter versiones demasiado técnicas/largas al buscador web porque reducen resultados a 0;
        // si la versión es corta (1-2 palabras) o hay ciudad, ayuda a orientar los resultados.
        $version = is_string($specs['version'] ?? null) ? trim($specs['version']) : '';

        if ($structuredParts !== [] && $version !== '' && str_word_count($version) <= 2 && mb_strlen($version) <= 18) {
            $structuredParts[] = $version;
        }

        $city = is_string($specs['city'] ?? null) ? trim($specs['city']) : '';

        if ($structuredParts !== [] && $city !== '' && count($structuredParts) <= 3) {
            $structuredParts[] = $city;
        }

        $base = $structuredParts !== [] ? implode(' ', $structuredParts) : $query;
        $clean = trim(preg_replace('/\s+/', ' ', $base) ?? $base);

        if ($platformId === 'kavak') {
            return preg_match('/\b(mx|m[eé]xico)\b/iu', $clean) ? $clean : $clean.' mx';
        }

        if ($platformId === 'autocosmos' && ! preg_match('/\busad[oa]s?\b/iu', $clean)) {
            return $clean.' usado';
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageResults
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<string, array<string, mixed>>
     */
    private function extractDirectCars(array $pageResults, array $platform, string $query, array $specs = []): array
    {
        $direct = [];
        $platformId = (string) ($platform['id'] ?? '');

        foreach ($pageResults as $result) {
            if (! is_array($result)) {
                continue;
            }

            $extracted = $result['json'] ?? $result['extract'] ?? data_get($result, 'data.json');
            $rawCars = is_array($extracted['autos'] ?? null)
                ? $extracted['autos']
                : (is_array($extracted['cars'] ?? null) ? $extracted['cars'] : (is_array($extracted) ? $extracted : []));

            $pageUrl = $result['url'] ?? data_get($result, 'metadata.sourceURL') ?? data_get($result, 'metadata.url');
            $markdown = (string) ($result['markdown'] ?? $result['content'] ?? '');
            $ogImage = data_get($result, 'metadata.ogImage') ?? data_get($result, 'metadata.image');
            $links = is_array($result['links'] ?? null)
                ? $result['links']
                : (is_array(data_get($result, 'data.links')) ? data_get($result, 'data.links') : []);
            $images = is_array($result['images'] ?? null)
                ? $result['images']
                : (is_array(data_get($result, 'data.images')) ? data_get($result, 'data.images') : []);

            $pageContext = [[
                'url' => (string) ($pageUrl ?? ''),
                'content' => $markdown,
                'links' => $links,
                'images' => $images,
            ]];

            foreach ($rawCars as $rawCar) {
                if (! is_array($rawCar) || empty($rawCar['title'])) {
                    continue;
                }

                $rawSourceUrl = filled($rawCar['sourceUrl'] ?? null) ? $rawCar['sourceUrl'] : $pageUrl;
                $rawImageUrl = filled($rawCar['imageUrl'] ?? null)
                    ? (string) $rawCar['imageUrl']
                    : ($this->normalizer->imageFromMarkdown($markdown, $platformId)
                        ?? ($images !== [] && is_string($images[0] ?? null) ? $images[0] : null)
                        ?? (is_string($ogImage) ? $ogImage : null));

                $car = $this->normalizer->normalize([
                    ...$rawCar,
                    'sourceUrl' => $rawSourceUrl,
                    'imageUrl' => $rawImageUrl,
                ], $platform, true);

                if (
                    $car
                    && $this->normalizer->matchesQuery($car, $query, $specs)
                    && $this->normalizer->isGroundedInPages($car, $pageContext)
                ) {
                    $direct[$car['id']] = $car;
                }
            }
        }

        return $direct;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageResults
     * @return array<int, array<string, mixed>>
     */
    private function extractPages(array $pageResults): array
    {
        return collect($pageResults)
            ->map(function (array $result): array {
                $links = is_array($result['links'] ?? null)
                    ? $result['links']
                    : (is_array(data_get($result, 'data.links')) ? data_get($result, 'data.links') : []);
                $images = is_array($result['images'] ?? null)
                    ? $result['images']
                    : (is_array(data_get($result, 'data.images')) ? data_get($result, 'data.images') : []);

                return [
                    'url' => (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? data_get($result, 'metadata.url') ?? ''),
                    'title' => (string) ($result['title'] ?? data_get($result, 'metadata.title') ?? ''),
                    'ogImage' => (string) (data_get($result, 'metadata.ogImage') ?? data_get($result, 'metadata.image') ?? ''),
                    'content' => (string) ($result['markdown'] ?? $result['content'] ?? $result['description'] ?? ''),
                    'links' => array_values(array_filter($links, 'is_string')),
                    'images' => array_values(array_filter($images, 'is_string')),
                ];
            })
            ->filter(fn (array $page): bool => $page['url'] !== '' || $page['content'] !== '' || $page['links'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function persistDebug(string $platformId, string $query, array $json): void
    {
        if (! config('external_search.debug')) {
            return;
        }

        try {
            ExternalSearchDebug::query()->create([
                'provider' => 'firecrawl:'.$platformId,
                'query_text' => $query,
                'item_count' => count($json),
                'payload' => $json,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // El diagnóstico nunca debe romper la búsqueda.
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<int, array<string, mixed>>
     */
    private function pageResults(array $json): array
    {
        foreach (['data', 'results', 'web'] as $key) {
            if (isset($json[$key]) && is_array($json[$key]) && array_is_list($json[$key])) {
                return $json[$key];
            }
        }

        if (isset($json['data']['web']) && is_array($json['data']['web'])) {
            return $json['data']['web'];
        }

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $value) {
                if (is_array($value) && array_is_list($value)) {
                    return $value;
                }
            }
        }

        return [];
    }

    /**
     * Hidrata enlaces individuales descubiertos en páginas de listado cuando la primera ronda trajo pocos autos.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $carsByPlatform
     * @param  array<string, array<int, array<string, mixed>>>  $pagesByPlatform
     * @param  array<string, array<string, mixed>>  $platformsById
     * @param  array<string, mixed>  $specs
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function hydrateDetailLinks(
        array $carsByPlatform,
        array $pagesByPlatform,
        array $platformsById,
        string $query,
        string $apiKey,
        int $connectTimeout,
        array $specs,
        float $startedAt,
    ): array {
        $candidatesByPlatform = $this->collectHydrationCandidates(
            $carsByPlatform,
            $pagesByPlatform,
            $platformsById,
            $query,
            $specs,
        );

        if ($candidatesByPlatform === []) {
            return $carsByPlatform;
        }

        $scrapedByPlatform = $this->scrapeUrlsBatch($candidatesByPlatform, $apiKey, $connectTimeout);

        if ($scrapedByPlatform === []) {
            return $carsByPlatform;
        }

        $extractedByPlatform = [];
        $pendingAiTasks = [];

        foreach ($scrapedByPlatform as $platformId => $scrapedPages) {
            $platform = $platformsById[$platformId] ?? null;

            if (! is_array($platform) || $scrapedPages === []) {
                continue;
            }

            $extractedByPage = [];
            $unextractedPages = [];

            foreach ($scrapedPages as $scrapedPage) {
                $fallbackCars = $this->normalizer->fallbackFromPages([$scrapedPage], $platform, $query, $specs);
                $grounded = array_values(array_filter(
                    $fallbackCars,
                    fn (array $car): bool => $this->normalizer->isGroundedInPages($car, [$scrapedPage]),
                ));

                if ($grounded !== []) {
                    foreach ($grounded as $car) {
                        $extractedByPage[$car['id']] = $car;
                    }
                } elseif (trim((string) ($scrapedPage['content'] ?? '')) !== '') {
                    $unextractedPages[] = $scrapedPage;
                }
            }

            $extractedByPlatform[$platformId] = $extractedByPage;

            if ($unextractedPages !== []) {
                $pendingAiTasks[$platformId] = [
                    'pages' => $unextractedPages,
                    'platform' => $platform,
                ];
            }
        }

        $aiHydrated = $pendingAiTasks !== [] && (microtime(true) - $startedAt) < (self::TIME_BUDGET_SECONDS - 3.0)
            ? $this->structurer->structureBatch($pendingAiTasks, $query, $specs)
            : [];

        foreach ($scrapedByPlatform as $platformId => $scrapedPages) {
            $merged = [];

            foreach ($carsByPlatform[$platformId] ?? [] as $car) {
                $merged[$car['id']] = $car;
            }

            foreach ($extractedByPlatform[$platformId] ?? [] as $car) {
                $merged[$car['id']] = $merged[$car['id']] ?? $car;
            }

            foreach ($aiHydrated[$platformId] ?? [] as $car) {
                if ($this->normalizer->isGroundedInPages($car, $scrapedPages)) {
                    $merged[$car['id']] = $merged[$car['id']] ?? $car;
                }
            }

            $carsByPlatform[$platformId] = array_slice(
                $this->normalizer->rankBySpecs(array_values($merged), $specs),
                0,
                self::MAX_PER_PLATFORM,
            );
        }

        return $carsByPlatform;
    }

    /**
     * Selecciona enlaces de detalle aún no cubiertos repartidos en round-robin entre plataformas con pocos autos.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $carsByPlatform
     * @param  array<string, array<int, array<string, mixed>>>  $pagesByPlatform
     * @param  array<string, array<string, mixed>>  $platformsById
     * @param  array<string, mixed>  $specs
     * @return array<string, array<int, string>>
     */
    private function collectHydrationCandidates(
        array $carsByPlatform,
        array $pagesByPlatform,
        array $platformsById,
        string $query,
        array $specs = [],
    ): array {
        $seenIds = [];
        $seenUrls = [];

        foreach ($carsByPlatform as $cars) {
            foreach ($cars as $car) {
                if (filled($car['id'] ?? null)) {
                    $seenIds[(string) $car['id']] = true;
                }

                $sourceUrl = (string) ($car['sourceUrl'] ?? '');

                if ($sourceUrl !== '') {
                    $seenUrls[$sourceUrl] = true;
                    $externalId = $this->normalizer->externalId($sourceUrl);

                    if ($externalId !== '') {
                        $seenIds[$externalId] = true;
                    }
                }
            }
        }

        $queuesByPlatform = [];

        foreach ($platformsById as $platformId => $platform) {
            if (count($carsByPlatform[$platformId] ?? []) >= 4) {
                continue;
            }

            $pages = $pagesByPlatform[$platformId] ?? [];

            if ($pages === []) {
                continue;
            }

            $domain = is_string($platform['domain'] ?? null) ? $platform['domain'] : null;

            foreach ($pages as $page) {
                $pageUrl = $this->normalizer->cleanUrl(
                    is_string($page['url'] ?? null) ? $page['url'] : null,
                    $domain,
                );

                if (
                    $pageUrl !== null
                    && $this->normalizer->isDetailUrl($pageUrl, $platformId)
                    && trim((string) ($page['content'] ?? '')) !== ''
                ) {
                    $seenUrls[$pageUrl] = true;
                }
            }

            $platformQueue = [];

            foreach ($pages as $page) {
                $rawCandidates = is_array($page['links'] ?? null) ? $page['links'] : [];
                $pageUrl = is_string($page['url'] ?? null) ? $page['url'] : '';

                if ($pageUrl !== '' && trim((string) ($page['content'] ?? '')) === '') {
                    array_unshift($rawCandidates, $pageUrl);
                }

                if (preg_match_all('/\(([^\s\)]+)\)/', (string) ($page['content'] ?? ''), $mdMatches)) {
                    foreach ($mdMatches[1] as $mdUrl) {
                        $rawCandidates[] = $mdUrl;
                    }
                }

                foreach ($rawCandidates as $rawLink) {
                    if (! is_string($rawLink)) {
                        continue;
                    }

                    $cleanLink = $this->normalizer->cleanUrl($rawLink, $domain);

                    if (
                        $cleanLink === null
                        || isset($seenUrls[$cleanLink])
                        || ! $this->normalizer->isDetailUrl($cleanLink, $platformId)
                        || ! $this->matchesLinkForQuery($cleanLink, $query, $specs)
                    ) {
                        continue;
                    }

                    $externalId = $this->normalizer->externalId($cleanLink);

                    if ($externalId !== '' && isset($seenIds[$externalId])) {
                        continue;
                    }

                    $seenUrls[$cleanLink] = true;

                    if ($externalId !== '') {
                        $seenIds[$externalId] = true;
                    }

                    $platformQueue[] = $cleanLink;

                    if (count($platformQueue) >= 4) {
                        break 2;
                    }
                }
            }

            if ($platformQueue !== []) {
                $queuesByPlatform[$platformId] = $platformQueue;
            }
        }

        $selected = [];
        $totalPicked = 0;

        while ($totalPicked < self::MAX_DETAIL_SCRAPES && $queuesByPlatform !== []) {
            foreach (array_keys($queuesByPlatform) as $platformId) {
                $nextUrl = array_shift($queuesByPlatform[$platformId]);

                if ($nextUrl !== null) {
                    $selected[$platformId][] = $nextUrl;
                    $totalPicked++;

                    if ($totalPicked >= self::MAX_DETAIL_SCRAPES) {
                        break 2;
                    }
                }

                if ($queuesByPlatform[$platformId] === []) {
                    unset($queuesByPlatform[$platformId]);
                }
            }
        }

        return $selected;
    }

    /**
     * Scrapea en paralelo las fichas individuales seleccionadas vía Firecrawl /v2/scrape.
     *
     * @param  array<string, array<int, string>>  $candidatesByPlatform
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function scrapeUrlsBatch(array $candidatesByPlatform, string $apiKey, int $connectTimeout): array
    {
        $poolKeys = [];

        foreach ($candidatesByPlatform as $platformId => $urls) {
            foreach ($urls as $index => $url) {
                $poolKeys["{$platformId}__{$index}"] = [
                    'platformId' => $platformId,
                    'url' => $url,
                ];
            }
        }

        if ($poolKeys === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($poolKeys, $apiKey, $connectTimeout): array {
                $requests = [];

                foreach ($poolKeys as $key => $item) {
                    $requests[] = $pool->as($key)
                        ->withToken($apiKey)
                        ->connectTimeout($connectTimeout)
                        ->timeout(8)
                        ->post('https://api.firecrawl.dev/v2/scrape', [
                            'url' => $item['url'],
                            'onlyMainContent' => true,
                            'excludeTags' => ['nav', 'footer', 'header', 'aside'],
                            'waitFor' => 600,
                            'timeout' => 8000,
                            'formats' => ['markdown', 'images'],
                        ]);
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }

        $scrapedByPlatform = [];

        foreach ($poolKeys as $key => $item) {
            $response = $responses[$key] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $json = $response->json();

            if (! is_array($json)) {
                continue;
            }

            $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
            $content = (string) ($data['markdown'] ?? $data['content'] ?? '');
            $images = is_array($data['images'] ?? null)
                ? array_values(array_filter($data['images'], 'is_string'))
                : [];
            $title = (string) ($data['title'] ?? data_get($data, 'metadata.title') ?? '');
            $ogImage = (string) (data_get($data, 'metadata.ogImage') ?? data_get($data, 'metadata.image') ?? '');

            if ($content === '' && $images === [] && $title === '') {
                continue;
            }

            $scrapedByPlatform[$item['platformId']][] = [
                'url' => $item['url'],
                'title' => $title,
                'ogImage' => $ogImage,
                'content' => $content,
                'links' => [$item['url']],
                'images' => $images,
            ];
        }

        return $scrapedByPlatform;
    }

    /**
     * Descarta URLs de detalle cuyo slug o año contradigan claramente la búsqueda del usuario.
     *
     * @param  array<string, mixed>  $specs
     */
    private function matchesLinkForQuery(string $url, string $query, array $specs = []): bool
    {
        $path = Str::lower(Str::ascii(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: ''))));

        if ($path === '') {
            return true;
        }

        $normalizedQuery = Str::lower(Str::ascii(
            implode(' ', array_filter([
                is_string($specs['brand'] ?? null) ? $specs['brand'] : null,
                is_string($specs['model'] ?? null) ? $specs['model'] : null,
                is_numeric($specs['year'] ?? null) ? (string) $specs['year'] : null,
            ])) ?: $query
        ));

        preg_match_all('/\b(19\d{2}|20\d{2})\b/', $normalizedQuery, $queryYearMatches);
        $queryYears = $queryYearMatches[1] ?? [];

        if ($queryYears !== []) {
            preg_match_all('/(?:^|[^0-9])(19\d{2}|20\d{2})(?:[^0-9]|$)/', $path, $pathYearMatches);
            $pathYears = $pathYearMatches[1] ?? [];

            if ($pathYears !== [] && array_intersect($queryYears, $pathYears) === []) {
                return false;
            }
        }

        $stopWords = [
            'auto', 'autos', 'carro', 'carros', 'camioneta', 'camionetas', 'vehiculo', 'vehiculos',
            'usado', 'usados', 'seminuevo', 'seminuevos', 'venta', 'en', 'de', 'la', 'el', 'los', 'las',
            'del', 'al', 'con', 'por', 'para', 'un', 'una', 'mx', 'mexico',
        ];

        $queryWords = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $normalizedQuery) ?: [],
            static fn (string $word): bool => strlen($word) >= 2
                && ! preg_match('/^(19\d{2}|20\d{2})$/', $word)
                && ! in_array($word, $stopWords, true),
        ));

        if ($queryWords === []) {
            return true;
        }

        $pathIgnore = [...$stopWords, 'vehicle', 'vehicles', 'p', 'articulo', 'mlm', 'id', 'comprar', 'detalle'];
        $slugWords = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $path) ?: [],
            static fn (string $word): bool => strlen($word) >= 2
                && ! ctype_digit($word)
                && ! in_array($word, $pathIgnore, true),
        ));

        if ($slugWords === []) {
            return true;
        }

        $compactPath = preg_replace('/[^a-z0-9]/', '', $path) ?? '';

        foreach (array_slice($queryWords, 0, 2) as $word) {
            $token = preg_replace('/[^a-z0-9]/', '', $word) ?? '';

            if ($token !== '' && ! str_contains($compactPath, $token)) {
                return false;
            }
        }

        return true;
    }
}
