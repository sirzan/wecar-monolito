<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $query = $validated['q'];
        $apiKey = config('services.openai.key');

        if (! $apiKey) {
            return response()->json([
                'source' => 'fallback',
                'filters' => ['q' => $query],
            ]);
        }

        $catalog = $this->catalogContext();

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un asistente de búsqueda de autos en México. Devuelve SOLO un objeto JSON con los filtros aplicables. Claves permitidas: q, brand, body, transmission, fuel, min_price, max_price, min_year, max_year. brand debe ser el slug de una marca del catálogo. No inventes valores.',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Catálogo disponible: '.json_encode($catalog, JSON_UNESCAPED_UNICODE)."\n\nBúsqueda del usuario: ".$query,
                        ],
                    ],
                ]);

            $response->throw();

            $content = (string) $response->json('choices.0.message.content', '{}');
            $filters = json_decode($content, true);

            if (! is_array($filters)) {
                throw new \RuntimeException('Respuesta inválida de IA.');
            }

            return response()->json([
                'source' => 'ai',
                'filters' => $this->sanitize($filters, $query),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'source' => 'fallback',
                'filters' => ['q' => $query],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogContext(): array
    {
        return [
            'brands' => CarBrand::query()->orderBy('name')->pluck('slug')->values(),
            'bodies' => Car::query()->published()->whereNotNull('body')->distinct()->pluck('body')->values(),
            'transmissions' => Car::query()->published()->whereNotNull('transmission')->distinct()->pluck('transmission')->values(),
            'fuels' => Car::query()->published()->whereNotNull('fuel')->distinct()->pluck('fuel')->values(),
            'price' => [
                'min' => (float) (Car::query()->published()->min('price') ?? 0),
                'max' => (float) (Car::query()->published()->max('price') ?? 0),
            ],
            'year' => [
                'min' => (int) (Car::query()->published()->min('year') ?? 2000),
                'max' => (int) (Car::query()->published()->max('year') ?? (int) date('Y')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitize(array $filters, string $query): array
    {
        $allowed = ['q', 'brand', 'body', 'transmission', 'fuel', 'min_price', 'max_price', 'min_year', 'max_year'];
        $clean = [];

        foreach ($allowed as $key) {
            if (! isset($filters[$key]) || $filters[$key] === '' || $filters[$key] === null) {
                continue;
            }

            $clean[$key] = is_scalar($filters[$key]) ? (string) $filters[$key] : null;
        }

        if (! isset($clean['q'])) {
            $clean['q'] = $query;
        }

        return $clean;
    }
}
