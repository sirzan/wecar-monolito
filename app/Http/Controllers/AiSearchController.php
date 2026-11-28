<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\ExternalListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiSearchController extends Controller
{
    /** @var array<string, string> */
    private const BRAND_ALIASES = [
        'vw' => 'volkswagen',
        'chevy' => 'chevrolet',
        'mercedes' => 'mercedes-benz',
        'mb' => 'mercedes-benz',
    ];

    /** @var array<string, string> */
    private const KNOWN_CITIES = [
        'ciudad de mexico' => 'Ciudad de México',
        'cdmx' => 'CDMX',
        'monterrey' => 'Monterrey',
        'guadalajara' => 'Guadalajara',
        'queretaro' => 'Querétaro',
        'puebla' => 'Puebla',
        'tijuana' => 'Tijuana',
        'leon' => 'León',
        'merida' => 'Mérida',
        'cancun' => 'Cancún',
        'toluca' => 'Toluca',
        'chihuahua' => 'Chihuahua',
        'aguascalientes' => 'Aguascalientes',
        'saltillo' => 'Saltillo',
        'san luis potosi' => 'San Luis Potosí',
        'hermosillo' => 'Hermosillo',
        'veracruz' => 'Veracruz',
        'morelia' => 'Morelia',
        'culiacan' => 'Culiacán',
        'zapopan' => 'Zapopan',
        'naucalpan' => 'Naucalpan',
        'tlalpan' => 'Tlalpan',
        'estado de mexico' => 'Estado de México',
        'edomex' => 'Estado de México',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $query = $validated['q'];
        $parsedLocal = $this->parseNaturalLanguage($query);
        $apiKey = config('services.openai.key');

        if (! $apiKey) {
            return response()->json([
                'source' => 'fallback',
                'filters' => $parsedLocal,
            ]);
        }

        $catalog = $this->catalogContext();

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un asistente de búsqueda de autos en México. Devuelve SOLO un objeto JSON con los filtros aplicables. Claves permitidas: q, brand, model, city, body, transmission, fuel, min_price, max_price, min_year, max_year. brand debe ser el slug de una marca del catálogo y model el nombre del modelo si se mencionó. Deja q vacío si el resto de filtros ya cubren la consulta. No inventes valores.',
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
                'filters' => $this->sanitize($filters, $parsedLocal, $query),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'source' => 'fallback',
                'filters' => $parsedLocal,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogContext(): array
    {
        $carCities = Car::query()->published()->whereNotNull('city')->distinct()->pluck('city');
        $extCities = ExternalListing::query()->whereNotNull('location')->distinct()->limit(30)->pluck('location');

        $minPrices = array_values(array_filter([
            Car::query()->published()->min('price'),
            ExternalListing::query()->whereNotNull('price')->min('price'),
        ], static fn ($v): bool => $v !== null && (float) $v > 0));

        $maxPrices = array_values(array_filter([
            Car::query()->published()->max('price'),
            ExternalListing::query()->whereNotNull('price')->max('price'),
        ], static fn ($v): bool => $v !== null && (float) $v > 0));

        return [
            'brands' => CarBrand::query()->orderBy('name')->pluck('slug')->values(),
            'cities' => $carCities->merge($extCities)->filter()->unique()->take(30)->values(),
            'bodies' => Car::query()->published()->whereNotNull('body')->distinct()->pluck('body')->values(),
            'transmissions' => Car::query()->published()->whereNotNull('transmission')->distinct()->pluck('transmission')->values(),
            'fuels' => Car::query()->published()->whereNotNull('fuel')->distinct()->pluck('fuel')->values(),
            'price' => [
                'min' => (float) ($minPrices !== [] ? min($minPrices) : 0),
                'max' => (float) ($maxPrices !== [] ? max($maxPrices) : 0),
            ],
            'year' => [
                'min' => (int) (Car::query()->published()->min('year') ?? ExternalListing::query()->min('year') ?? 2000),
                'max' => (int) (Car::query()->published()->max('year') ?? ExternalListing::query()->max('year') ?? (int) date('Y')),
            ],
        ];
    }

    /**
     * Intérprete determinista en español que funciona sobre autos internos y externos incluso sin OPENAI_API_KEY.
     *
     * @return array<string, string>
     */
    private function parseNaturalLanguage(string $query): array
    {
        $filters = [];
        $working = Str::lower(Str::ascii($query));
        $currentYear = (int) date('Y');

        // 1. Precios: "hasta $350,000", "menos de 400 mil", "maximo 450000", "desde $200,000"
        if (preg_match('/\b(?:hasta|maximo|max|menos\s+de|tope(?:\s+de)?)\s*\$?\s*(\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)\s*(mil|k)?\b/i', $working, $match)) {
            $amount = $this->parseNumericPrice($match[1], $match[2] ?? '');

            if ($amount !== null) {
                $filters['max_price'] = (string) $amount;
                $working = str_replace($match[0], ' ', $working);
            }
        }

        if (preg_match('/\b(?:desde|minimo|min|mas\s+de|arriba\s+de)\s*\$?\s*(\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)\s*(mil|k)?\b/i', $working, $match)) {
            $amount = $this->parseNumericPrice($match[1], $match[2] ?? '');

            if ($amount !== null) {
                $filters['min_price'] = (string) $amount;
                $working = str_replace($match[0], ' ', $working);
            }
        }

        // 2. Rango o año exacto: "2020 a 2023", "desde 2021", "2022"
        if (preg_match('/\b(199\d|20[0-3]\d)\s*(?:a|-|al|hasta)\s*(199\d|20[0-3]\d)\b/', $working, $match)) {
            $y1 = (int) $match[1];
            $y2 = (int) $match[2];

            if ($y1 >= 1990 && $y2 <= $currentYear + 1) {
                $filters['min_year'] = (string) min($y1, $y2);
                $filters['max_year'] = (string) max($y1, $y2);
                $working = str_replace($match[0], ' ', $working);
            }
        } elseif (preg_match('/\b(199\d|20[0-3]\d)\b/', $working, $match)) {
            $year = (int) $match[1];

            if ($year >= 1990 && $year <= $currentYear + 1) {
                $filters['min_year'] = (string) $year;
                $filters['max_year'] = (string) $year;
                $working = preg_replace('/\b'.preg_quote($match[1], '/').'\b/', ' ', $working) ?? $working;
            }
        }

        // 3. Marca y modelo del catálogo
        $brands = CarBrand::query()->orderByRaw('LENGTH(name) DESC')->get(['id', 'name', 'slug']);
        $matchedBrand = null;

        foreach (self::BRAND_ALIASES as $alias => $targetSlug) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/', $working) === 1) {
                $matchedBrand = $brands->firstWhere('slug', $targetSlug);

                if ($matchedBrand !== null) {
                    $working = preg_replace('/\b'.preg_quote($alias, '/').'\b/', ' ', $working) ?? $working;
                    break;
                }
            }
        }

        if ($matchedBrand === null) {
            foreach ($brands as $brand) {
                $brandNeedle = Str::lower(Str::ascii($brand->name));

                if ($brandNeedle !== '' && preg_match('/\b'.preg_quote($brandNeedle, '/').'\b/', $working) === 1) {
                    $matchedBrand = $brand;
                    $working = preg_replace('/\b'.preg_quote($brandNeedle, '/').'\b/', ' ', $working) ?? $working;
                    break;
                }
            }
        }

        if ($matchedBrand !== null) {
            $filters['brand'] = (string) $matchedBrand->slug;

            $models = CarModel::query()
                ->where('brand_id', $matchedBrand->getKey())
                ->orderByRaw('LENGTH(name) DESC')
                ->get(['id', 'name']);

            foreach ($models as $model) {
                $modelNeedle = Str::lower(Str::ascii($model->name));

                if ($modelNeedle !== '' && preg_match('/\b'.preg_quote($modelNeedle, '/').'\b/', $working) === 1) {
                    $filters['model'] = (string) $model->getKey();
                    $working = preg_replace('/\b'.preg_quote($modelNeedle, '/').'\b/', ' ', $working) ?? $working;
                    break;
                }
            }
        }

        // 4. Ciudad
        foreach (self::KNOWN_CITIES as $needle => $label) {
            if (preg_match('/\b(?:en\s+)?'.preg_quote($needle, '/').'\b/', $working, $match) === 1) {
                $filters['city'] = $label;
                $working = str_replace($match[0], ' ', $working);
                break;
            }
        }

        // 5. Carrocería, transmisión y combustible
        $bodies = [
            'suv' => 'SUV',
            'sedan' => 'Sedán',
            'pickup' => 'Pickup',
            'pick up' => 'Pickup',
            'hatchback' => 'Hatchback',
            'coupe' => 'Coupé',
            'minivan' => 'Minivan',
        ];

        foreach ($bodies as $needle => $label) {
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/', $working) === 1) {
                $filters['body'] = $label;
                $working = preg_replace('/\b'.preg_quote($needle, '/').'\b/', ' ', $working) ?? $working;
                break;
            }
        }

        if (preg_match('/\b(automatic[ao]|cvt)\b/', $working, $match) === 1) {
            $filters['transmission'] = 'Automática';
            $working = str_replace($match[0], ' ', $working);
        } elseif (preg_match('/\b(manual|estandar)\b/', $working, $match) === 1) {
            $filters['transmission'] = 'Manual';
            $working = str_replace($match[0], ' ', $working);
        }

        if (preg_match('/\b(hibrid[ao]|hybrid)\b/', $working, $match) === 1) {
            $filters['fuel'] = 'Híbrido';
            $working = str_replace($match[0], ' ', $working);
        } elseif (preg_match('/\b(electric[ao])\b/', $working, $match) === 1) {
            $filters['fuel'] = 'Eléctrico';
            $working = str_replace($match[0], ' ', $working);
        } elseif (preg_match('/\b(diesel)\b/', $working, $match) === 1) {
            $filters['fuel'] = 'Diésel';
            $working = str_replace($match[0], ' ', $working);
        }

        // 6. Texto residual (solo si quedan palabras útiles que no sean conectores)
        $stopwords = [
            'en', 'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'con', 'sin', 'para', 'por',
            'auto', 'autos', 'carro', 'carros', 'camioneta', 'camionetas', 'seminuevo', 'seminuevos',
            'usado', 'usados', 'pesos', 'mxn', 'hasta', 'desde', 'menos', 'mas', 'maximo', 'minimo',
            'busco', 'quiero', 'necesito',
        ];

        $leftover = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $working) ?: [],
            static fn (string $token): bool => strlen($token) >= 2 && ! in_array($token, $stopwords, true),
        ));

        if ($leftover !== []) {
            $filters['q'] = implode(' ', $leftover);
        } elseif ($filters === []) {
            $filters['q'] = trim($query);
        }

        return $filters;
    }

    private function parseNumericPrice(string $rawNumber, string $unit): ?int
    {
        $numeric = (float) str_replace(',', '', $rawNumber);

        if ($numeric <= 0) {
            return null;
        }

        if (strtolower($unit) === 'mil' || strtolower($unit) === 'k') {
            $numeric *= 1000;
        }

        $rounded = (int) round($numeric);

        return ($rounded >= 25_000 && $rounded <= 20_000_000) ? $rounded : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $fallback
     * @return array<string, mixed>
     */
    private function sanitize(array $filters, array $fallback, string $query): array
    {
        $allowed = ['q', 'brand', 'model', 'city', 'body', 'transmission', 'fuel', 'min_price', 'max_price', 'min_year', 'max_year'];
        $clean = [];

        foreach ($allowed as $key) {
            if (! isset($filters[$key]) || $filters[$key] === '' || $filters[$key] === null) {
                continue;
            }

            $clean[$key] = is_scalar($filters[$key]) ? trim((string) $filters[$key]) : null;
        }

        // Si IA devolvió el nombre del modelo en vez de UUID, intentamos mapearlo al ID real del catálogo.
        if (isset($clean['model']) && ! Str::isUuid((string) $clean['model'])) {
            $brandSlug = (string) ($clean['brand'] ?? $fallback['brand'] ?? '');
            $brandId = $brandSlug !== '' ? CarBrand::query()->where('slug', $brandSlug)->value('id') : null;
            $modelRow = CarModel::query()
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->whereLike('name', '%'.$clean['model'].'%')
                ->first(['id']);

            if ($modelRow !== null) {
                $clean['model'] = (string) $modelRow->getKey();
            } else {
                unset($clean['model']);
            }
        }

        foreach (['brand', 'model', 'city', 'min_price', 'max_price', 'min_year', 'max_year'] as $key) {
            if (! isset($clean[$key]) && isset($fallback[$key])) {
                $clean[$key] = $fallback[$key];
            }
        }

        if ($clean === []) {
            $clean['q'] = $query;
        }

        return $clean;
    }
}
