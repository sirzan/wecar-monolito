<?php

namespace App\Services\ExternalSearch;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAiStructurer
{
    public function __construct(private readonly ExternalCarNormalizer $normalizer) {}

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    public function structure(array $pages, array $platform, string $query): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '' || $pages === []) {
            return [];
        }

        $payload = $this->buildPayload($pages, $platform, $query);

        if ($payload === null) {
            return [];
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(6)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            return $this->parseResponse($response, $platform, $query, $pages);
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * Procesa en paralelo el respaldo de OpenAI para múltiples plataformas.
     *
     * @param  array<string, array{pages: array<int, array<string, mixed>>, platform: array<string, mixed>}>  $tasks
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function structureBatch(array $tasks, string $query): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '' || $tasks === []) {
            return [];
        }

        $payloads = [];

        foreach ($tasks as $platformId => $task) {
            $payload = $this->buildPayload($task['pages'], $task['platform'], $query);

            if ($payload !== null) {
                $payloads[$platformId] = $payload;
            }
        }

        if ($payloads === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($payloads, $apiKey): array {
                $requests = [];

                foreach ($payloads as $platformId => $payload) {
                    $requests[] = $pool->as((string) $platformId)
                        ->withToken($apiKey)
                        ->connectTimeout(6)
                        ->timeout(25)
                        ->post('https://api.openai.com/v1/chat/completions', $payload);
                }

                return $requests;
            });

            $results = [];

            foreach ($payloads as $platformId => $payload) {
                $response = $responses[$platformId] ?? null;
                $results[$platformId] = $response instanceof Response
                    ? $this->parseResponse(
                        $response,
                        $tasks[$platformId]['platform'],
                        $query,
                        $tasks[$platformId]['pages'],
                    )
                    : [];
            }

            return $results;
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>|null
     */
    private function buildPayload(array $pages, array $platform, string $query): ?array
    {
        $combined = '';
        $maxChars = 48_000;
        $perPageLimit = 12_000;
        $platformId = (string) ($platform['id'] ?? '');
        $domain = isset($platform['domain']) ? (string) $platform['domain'] : null;

        $preparedPages = [];

        foreach ($pages as $page) {
            $pageUrl = trim((string) ($page['url'] ?? ''));
            $isDetailPage = $pageUrl !== '' && $this->normalizer->isDetailUrl($pageUrl, $platformId);
            $detailLinks = $this->filterDetailLinks($page['links'] ?? [], $platformId, $domain);
            $validImages = $this->filterValidImages($page['images'] ?? [], $platformId);

            $preparedPages[] = [
                'page' => $page,
                'pageUrl' => $pageUrl,
                'isDetailPage' => $isDetailPage,
                'detailLinks' => $detailLinks,
                'validImages' => $validImages,
                'score' => ($isDetailPage ? 1000 : 0) + (count($detailLinks) * 10) + count($validImages),
            ];
        }

        usort($preparedPages, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        foreach ($preparedPages as $entry) {
            $page = $entry['page'];
            $content = trim((string) ($page['content'] ?? ''));
            $detailLinks = $entry['detailLinks'];
            $validImages = $entry['validImages'];

            if ($content === '' && $detailLinks === []) {
                continue;
            }

            $ogImage = trim((string) ($page['ogImage'] ?? ''));
            $focusedContent = $content !== '' ? $this->focusListingContent($content, $perPageLimit) : '';
            $chunk = "\n\n=== PÁGINA: ".$entry['pageUrl']." ===\n"
                .(! empty($page['title']) ? 'Título: '.$page['title']."\n" : '')
                .($ogImage !== '' && $this->normalizer->isValidImage($ogImage, $platformId)
                    ? 'Imagen destacada: '.$ogImage."\n"
                    : '')
                .($detailLinks !== [] ? "Enlaces de detalle detectados:\n- ".implode("\n- ", $detailLinks)."\n" : '')
                .($validImages !== [] ? "Imágenes detectadas:\n- ".implode("\n- ", $this->annotateImages($validImages))."\n" : '')
                .($focusedContent !== '' ? $focusedContent : '');

            if (mb_strlen($combined.$chunk) > $maxChars) {
                break;
            }

            $combined .= $chunk;
        }

        if (trim($combined) === '') {
            return null;
        }

        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.1,
            'max_tokens' => 3_000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'external_car_listings',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'cars' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'price' => ['type' => ['number', 'null']],
                                        'year' => ['type' => ['integer', 'null']],
                                        'km' => ['type' => ['integer', 'null']],
                                        'location' => ['type' => ['string', 'null']],
                                        'imageUrl' => ['type' => ['string', 'null']],
                                        'sourceUrl' => ['type' => 'string'],
                                    ],
                                    'required' => ['title', 'price', 'year', 'km', 'location', 'imageUrl', 'sourceUrl'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['cars'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => implode(' ', [
                        'Eres un extractor estricto de anuncios INDIVIDUALES de autos en venta de '.$platform['name'].' en México.',
                        'Extrae únicamente vehículos reales cuya marca, modelo y año coincidan EXACTAMENTE con la búsqueda: "'.$query.'".',
                        'IGNORA autos recomendados o similares de otra marca o de otro año distinto al buscado.',
                        'REGLA CRÍTICA: NUNCA devuelvas la página de listado/categoría (ej. URLs como /usados/..., /mx/seminuevos/ford/2024 o títulos como "Autos Ford 2024..."). Si una página es un listado con múltiples tarjetas, extrae CADA vehículo individual por separado.',
                        'PROHIBIDO inventar URLs o IDs de ejemplo (como example.com, MLM123456, MLM654321).',
                        'sourceUrl debe copiarse literalmente de "PÁGINA" (solo si es ficha individual), "Enlaces de detalle detectados" o del contenido, apuntando al anuncio específico del auto.',
                        'price (entero MXN > 25000) e imageUrl son OBLIGATORIOS: copia la foto real del auto desde "Imágenes detectadas", "Imagen destacada" o el contenido (nunca banners ni placeholders). Si falta precio real o imagen real, omite el elemento.',
                        'Extrae hasta 15 autos individuales diferentes.',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => "Contenido:\n\n".$combined,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function parseResponse(Response $response, array $platform, string $query, array $pages = []): array
    {
        if (! $response->successful()) {
            return [];
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($content, true);
        $rawCars = is_array($decoded['cars'] ?? null) ? $decoded['cars'] : [];
        $cars = [];

        foreach ($rawCars as $rawCar) {
            if (! is_array($rawCar)) {
                continue;
            }

            $car = $this->normalizer->normalize($rawCar, $platform, true);

            if (! $car || ! $this->normalizer->matchesQuery($car, $query)) {
                continue;
            }

            if ($pages !== [] && ! $this->normalizer->isGroundedInPages($car, $pages)) {
                continue;
            }

            $cars[$car['id']] = $car;
        }

        return array_values($cars);
    }

    /**
     * @return array<int, string>
     */
    private function filterDetailLinks(mixed $rawLinks, string $platformId, ?string $domain): array
    {
        if (! is_array($rawLinks)) {
            return [];
        }

        $links = [];

        foreach ($rawLinks as $rawLink) {
            if (! is_string($rawLink)) {
                continue;
            }

            $clean = $this->normalizer->cleanUrl($rawLink, $domain);

            if ($clean && $this->normalizer->isDetailUrl($clean, $platformId)) {
                $links[$clean] = $clean;
            }

            if (count($links) >= 40) {
                break;
            }
        }

        return array_values($links);
    }

    /**
     * @return array<int, string>
     */
    private function filterValidImages(mixed $rawImages, string $platformId): array
    {
        if (! is_array($rawImages)) {
            return [];
        }

        $images = [];

        foreach ($rawImages as $rawImage) {
            if (! is_string($rawImage)) {
                continue;
            }

            $clean = $this->normalizer->cleanImageUrl($rawImage);

            if ($clean && $this->normalizer->isValidImage($clean, $platformId)) {
                $images[$clean] = $clean;
            }

            if (count($images) >= 40) {
                break;
            }
        }

        return array_values($images);
    }

    private function focusListingContent(string $content, int $limit): string
    {
        if (mb_strlen($content) <= $limit) {
            return $content;
        }

        if (preg_match('/(?:\$\s*\d{2,3},\d{3}|\/vehicle\/|-id-\d+|MLM-?\d+|\/auto\/usado\/)/i', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $byteOffset = $match[0][1];
            $charOffset = mb_strlen(substr($content, 0, $byteOffset));
            $start = max(0, $charOffset - 350);

            return mb_substr($content, $start, $limit);
        }

        return mb_substr($content, 0, $limit);
    }

    /**
     * @param  array<int, string>  $images
     * @return array<int, string>
     */
    private function annotateImages(array $images): array
    {
        return array_map(function (string $url): string {
            $kavakKey = $this->normalizer->extractKavakImageKey($url);

            if ($kavakKey !== null && preg_match('/images\/(\d+)\//', $kavakKey, $match) === 1) {
                return '(ID '.$match[1].') '.$url;
            }

            return $url;
        }, $images);
    }
}
