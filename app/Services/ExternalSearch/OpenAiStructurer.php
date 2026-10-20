<?php

namespace App\Services\ExternalSearch;

use Illuminate\Support\Facades\Http;

class OpenAiStructurer
{
    public function __construct(private readonly ExternalCarNormalizer $normalizer) {}

    /**
     * @param  array<int, array<string, string>>  $pages
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    public function structure(array $pages, array $platform, string $query): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '' || $pages === []) {
            return [];
        }

        $combined = '';
        $maxChars = 30000;

        foreach ($pages as $page) {
            $chunk = "\n\n=== PÁGINA: ".$page['url']." ===\n"
                .($page['title'] !== '' ? 'Título: '.$page['title']."\n" : '')
                .$page['content'];

            if (mb_strlen($combined.$chunk) > $maxChars) {
                break;
            }

            $combined .= $chunk;
        }

        if (trim($combined) === '') {
            return [];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'max_tokens' => 1500,
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
                                'Eres un extractor estricto de anuncios individuales de autos seminuevos en venta de la plataforma '.$platform['name'].'.',
                                'Extrae únicamente publicaciones individuales reales que correspondan EXACTAMENTE a la búsqueda: "'.$query.'".',
                                'sourceUrl debe ser el enlace directo a la ficha del vehículo, nunca una búsqueda, categoría o página principal.',
                                'imageUrl debe ser la foto principal del auto, no logos ni banners. Si no hay foto, no incluyas el auto.',
                                'Máximo 6 autos. No inventes datos ni URLs.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => "Contenido:\n\n".$combined,
                        ],
                    ],
                ]);

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

                if ($car) {
                    $cars[$car['id']] = $car;
                }
            }

            return array_values($cars);
        } catch (\Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
