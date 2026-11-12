<?php

namespace App\Services\ExternalSearch\Providers;

use App\Models\ExternalSearchDebug;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use App\Services\ExternalSearch\OpenAiStructurer;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FirecrawlProvider
{
    private const MAX_PER_PLATFORM = 10;

    private const AI_COMPLEMENT_THRESHOLD = 15;

    private const FALLBACK_COMPLEMENT_THRESHOLD = 10;

    private const DETAIL_HYDRATE_THRESHOLD = 12;

    private const MAX_DETAIL_SCRAPES = 8;

    public function __construct(
        private readonly ExternalCarNormalizer $normalizer,
        private readonly OpenAiStructurer $structurer,
    ) {}

    /**
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $platform): array
    {
        $platformId = (string) ($platform['id'] ?? 'firecrawl');
        $batch = $this->searchBatch($query, [$platform]);

        return $batch[$platformId] ?? [];
    }

    /**
     * Consulta en paralelo todas las plataformas Firecrawl y combina extracción directa + OpenAI + fallback Markdown.
     *
     * @param  array<int, array<string, mixed>>  $platforms
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function searchBatch(string $query, array $platforms): array
    {
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '' || $platforms === []) {
            return [];
        }

        $connectTimeout = (int) config('external_search.connect_timeout', 5);
        $timeout = max(10, (int) config('external_search.timeout', 35));

        try {
            $responses = Http::pool(function (Pool $pool) use ($platforms, $query, $apiKey, $connectTimeout, $timeout): array {
                $requests = [];

                foreach ($platforms as $platform) {
                    $platformId = (string) $platform['id'];
                    $requests[] = $pool->as($platformId)
                        ->withToken($apiKey)
                        ->connectTimeout($connectTimeout)
                        ->timeout($timeout)
                        ->post('https://api.firecrawl.dev/v2/search', $this->buildPayload($query, $platform));
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return [];
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
                report($response instanceof \Throwable
                    ? $response
                    : new \RuntimeException('Firecrawl sin respuesta válida para '.$platform['name']));

                continue;
            }

            if (! $response->successful()) {
                report(new \RuntimeException('Firecrawl '.$response->status().' para '.$platform['name']));

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

            $direct = $this->extractDirectCars($pageResults, $platform, $query);
            $pages = $this->extractPages($pageResults);

            $carsByPlatform[$platformId] = $direct;
            $pagesByPlatform[$platformId] = $pages;

            if (count($direct) < self::AI_COMPLEMENT_THRESHOLD && $pages !== []) {
                $pendingAiTasks[$platformId] = [
                    'pages' => $pages,
                    'platform' => $platform,
                ];
            }
        }

        // Complementa en paralelo con OpenAI cuando Firecrawl devolvió pocos autos o solo páginas de listado.
        $aiResults = $pendingAiTasks !== []
            ? $this->structurer->structureBatch($pendingAiTasks, $query)
            : [];

        foreach ($platformsById as $platformId => $platform) {
            $merged = $carsByPlatform[$platformId] ?? [];

            foreach ($aiResults[$platformId] ?? [] as $car) {
                $merged[$car['id']] = $merged[$car['id']] ?? $car;
            }

            $pages = $pagesByPlatform[$platformId] ?? [];

            if (count($merged) < self::FALLBACK_COMPLEMENT_THRESHOLD && $pages !== []) {
                foreach ($this->normalizer->fallbackFromPages($pages, $platform, $query) as $car) {
                    $merged[$car['id']] = $merged[$car['id']] ?? $car;
                }
            }

            if ($pages !== []) {
                $merged = array_filter(
                    $merged,
                    fn (array $car): bool => $this->normalizer->isGroundedInPages($car, $pages),
                );
            }

            $carsByPlatform[$platformId] = array_slice(
                $this->normalizer->sortByPlatform(array_values($merged)),
                0,
                self::MAX_PER_PLATFORM,
            );
        }

        $totalFound = array_sum(array_map('count', $carsByPlatform));

        if ($totalFound < self::DETAIL_HYDRATE_THRESHOLD) {
            $carsByPlatform = $this->hydrateDetailLinks(
                $carsByPlatform,
                $pagesByPlatform,
                $platformsById,
                $query,
                $apiKey,
                $connectTimeout,
            );
        }

        return $carsByPlatform;
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>
     */
    private function buildPayload(string $query, array $platform): array
    {
        $domains = is_array($platform['domains'] ?? null) && $platform['domains'] !== []
            ? array_values(array_filter(array_map('strval', $platform['domains'])))
            : [(string) $platform['domain']];
        $platformId = (string) ($platform['id'] ?? '');

        return [
            'query' => $this->buildSearchQuery($query, $platformId),
            'limit' => 10,
            'country' => 'MX',
            'location' => 'Mexico',
            'ignoreInvalidURLs' => true,
            'timeout' => 30000,
            'includeDomains' => $domains,
            'scrapeOptions' => [
                'onlyMainContent' => true,
                'excludeTags' => ['nav', 'footer', 'header', 'aside'],
                'waitFor' => 2000,
                'formats' => ['markdown', 'links', 'images'],
            ],
        ];
    }

    private function buildSearchQuery(string $query, string $platformId = ''): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $query) ?? $query);

        if ($platformId === 'kavak') {
            return preg_match('/\b(mx|m[eé]xico)\b/iu', $clean) ? $clean : $clean.' mx';
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageResults
     * @param  array<string, mixed>  $platform
     * @return array<string, array<string, mixed>>
     */
    private function extractDirectCars(array $pageResults, array $platform, string $query): array
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
                    && $this->normalizer->matchesQuery($car, $query)
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function hydrateDetailLinks(
        array $carsByPlatform,
        array $pagesByPlatform,
        array $platformsById,
        string $query,
        string $apiKey,
        int $connectTimeout,
    ): array {
        $candidatesByPlatform = $this->collectHydrationCandidates(
            $carsByPlatform,
            $pagesByPlatform,
            $platformsById,
            $query,
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
                $fallbackCars = $this->normalizer->fallbackFromPages([$scrapedPage], $platform, $query);
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

        $aiHydrated = $pendingAiTasks !== []
            ? $this->structurer->structureBatch($pendingAiTasks, $query)
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
                $this->normalizer->sortByPlatform(array_values($merged)),
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
     * @return array<string, array<int, string>>
     */
    private function collectHydrationCandidates(
        array $carsByPlatform,
        array $pagesByPlatform,
        array $platformsById,
        string $query,
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
                        || ! $this->matchesLinkForQuery($cleanLink, $query)
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
                        ->timeout(15)
                        ->post('https://api.firecrawl.dev/v2/scrape', [
                            'url' => $item['url'],
                            'onlyMainContent' => true,
                            'excludeTags' => ['nav', 'footer', 'header', 'aside'],
                            'waitFor' => 1500,
                            'timeout' => 15000,
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
     */
    private function matchesLinkForQuery(string $url, string $query): bool
    {
        $path = Str::lower(Str::ascii(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: ''))));

        if ($path === '') {
            return true;
        }

        $normalizedQuery = Str::lower(Str::ascii($query));
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
        $primaryToken = preg_replace('/[^a-z0-9]/', '', $queryWords[0]) ?? '';

        return $primaryToken === '' || str_contains($compactPath, $primaryToken);
    }
}
