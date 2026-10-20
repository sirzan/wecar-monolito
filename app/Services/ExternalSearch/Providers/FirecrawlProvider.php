<?php

namespace App\Services\ExternalSearch\Providers;

use App\Models\ExternalSearchDebug;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use App\Services\ExternalSearch\OpenAiStructurer;
use Illuminate\Support\Facades\Http;

class FirecrawlProvider
{
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
        $apiKey = (string) config('services.firecrawl.key');

        if ($apiKey === '') {
            return [];
        }

        $searchQuery = $query.' autos seminuevos en venta México';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.firecrawl.dev/v2/search', [
                    'query' => $searchQuery,
                    'limit' => 5,
                    'includeDomains' => [$platform['domain']],
                    'scrapeOptions' => [
                        'onlyMainContent' => true,
                        'formats' => [
                            [
                                'type' => 'json',
                                'prompt' => implode(' ', [
                                    'Extrae anuncios individuales de autos seminuevos EN VENTA en '.$platform['name'].' que correspondan EXACTAMENTE a la búsqueda "'.$query.'".',
                                    'REGLA CRÍTICA: solo autos específicos con enlace individual a su ficha. NUNCA enlaces de búsqueda general, categorías, listados ni páginas de marca.',
                                    'El título DEBE incluir la marca y el modelo buscados; si la búsqueda incluye un año, el auto debe ser de ese año.',
                                    'Para cada auto extrae: title, price (MXN entero), year, km, location, imageUrl (foto principal real) y sourceUrl (ficha individual).',
                                    'Si no encuentras autos que coincidan y tengan foto, devuelve una lista vacía.',
                                ]),
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'autos' => [
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
                                                'required' => ['title', 'sourceUrl'],
                                            ],
                                        ],
                                    ],
                                    'required' => ['autos'],
                                ],
                            ],
                            ['type' => 'markdown'],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                report(new \RuntimeException('Firecrawl '.$response->status().' para '.$platform['name']));

                return [];
            }

            $json = $response->json();

            if (config('external_search.debug')) {
                try {
                    ExternalSearchDebug::query()->create([
                        'provider' => 'firecrawl:'.$platform['id'],
                        'query_text' => $query,
                        'item_count' => is_array($json) ? count($json) : 0,
                        'payload' => $json,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // El diagnóstico nunca debe romper la búsqueda.
                }
            }

            $pageResults = $this->pageResults($json);

            if ($pageResults === []) {
                return [];
            }

            $direct = [];

            foreach ($pageResults as $result) {
                $extracted = $result['json'] ?? $result['extract'] ?? data_get($result, 'data.json');
                $rawCars = is_array($extracted['autos'] ?? null)
                    ? $extracted['autos']
                    : (is_array($extracted['cars'] ?? null) ? $extracted['cars'] : (is_array($extracted) ? $extracted : []));

                foreach ($rawCars as $rawCar) {
                    if (! is_array($rawCar) || empty($rawCar['title'])) {
                        continue;
                    }

                    $car = $this->normalizer->normalize([
                        ...$rawCar,
                        'sourceUrl' => $rawCar['sourceUrl'] ?? $result['url'] ?? data_get($result, 'metadata.sourceURL') ?? data_get($result, 'metadata.url'),
                        'imageUrl' => $rawCar['imageUrl'] ?? $this->normalizer->imageFromMarkdown((string) ($result['markdown'] ?? $result['content'] ?? '')),
                    ], $platform, true);

                    if ($car) {
                        $direct[$car['id']] = $car;
                    }
                }
            }

            if ($direct !== []) {
                return array_slice(array_values($direct), 0, 6);
            }

            $pages = collect($pageResults)
                ->map(fn (array $result) => [
                    'url' => (string) ($result['url'] ?? data_get($result, 'metadata.sourceURL') ?? data_get($result, 'metadata.url') ?? ''),
                    'title' => (string) ($result['title'] ?? ''),
                    'content' => (string) ($result['markdown'] ?? $result['content'] ?? $result['description'] ?? ''),
                ])
                ->filter(fn (array $page) => $page['url'] !== '' || $page['content'] !== '')
                ->values()
                ->all();

            $structured = $this->structurer->structure($pages, $platform, $query);

            if ($structured !== []) {
                return array_slice($structured, 0, 6);
            }

            return array_slice($this->normalizer->fallbackFromPages($pages, $platform, $query), 0, 6);
        } catch (\Throwable $exception) {
            report($exception);

            return [];
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
}
