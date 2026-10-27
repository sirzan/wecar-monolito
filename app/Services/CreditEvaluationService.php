<?php

namespace App\Services;

use App\Models\Financiera;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreditEvaluationService
{
    public function __construct(
        private readonly CreditScoringService $scoring,
        private readonly CreditOfferService $offers,
    ) {}

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function evaluate(array $auto, array $profile): array
    {
        $cacheKey = 'credit-evaluation:v2:'.md5(json_encode([$auto, $profile]));

        return Cache::remember($cacheKey, 3600, function () use ($auto, $profile) {
            $financieras = Financiera::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            if ($financieras->isEmpty()) {
                $financieras = $this->fallbackFinancieras();
            }

            $input = $this->input($auto, $profile);
            $scoring = $this->scoring->calculate($input);
            $allOffers = $this->offers->generate($input, $scoring['score'], $financieras);

            $purchaseOffers = collect($allOffers)
                ->reject(fn (array $offer) => ($offer['tipo'] ?? null) === 'prendaria')
                ->values();

            $pawnOffers = collect($allOffers)
                ->filter(fn (array $offer) => ($offer['tipo'] ?? null) === 'prendaria')
                ->values();

            $result = $this->result($auto, $profile, $input, $scoring, $purchaseOffers, $pawnOffers);

            $apiKey = config('services.openai.key');

            if ($apiKey) {
                try {
                    $narrative = $this->narrativeWithOpenAi($auto, $profile, $purchaseOffers, (string) $apiKey);

                    if ($narrative !== null) {
                        $result['resumenEjecutivo'] = $narrative['resumenEjecutivo'];
                        $result['recomendaciones'] = $narrative['recomendaciones'];
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function input(array $auto, array $profile): array
    {
        $type = (string) ($auto['tipo'] ?? 'seminuevo');

        return [
            'ingreso' => (float) ($profile['ingresoMensual'] ?? 0),
            'edad' => (int) ($profile['edad'] ?? 0),
            'antiguedad' => (float) ($profile['antiguedadLaboralAnios'] ?? 0),
            'empleo' => (string) ($profile['tipoEmpleo'] ?? 'fijo'),
            'historial' => (string) ($profile['historialBuro'] ?? 'bueno'),
            'deudas' => (float) ($profile['deudasMensuales'] ?? 0),
            'precio' => (float) ($auto['precio'] ?? 0),
            'enganche' => (float) ($profile['enganche'] ?? 0),
            'plazo' => (int) ($profile['plazoMeses'] ?? 48),
            'car_year' => (int) ($auto['anio'] ?? now()->year - 3),
            'brand' => (string) ($auto['marca'] ?? ''),
            'vehicle_condition' => $type === 'nuevo' ? 'new' : 'used',
        ];
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $input
     * @param  array{score: int, dti: float, monthly_payment: float}  $scoring
     * @param  Collection<int, array<string, mixed>>  $purchaseOffers
     * @param  Collection<int, array<string, mixed>>  $pawnOffers
     * @return array<string, mixed>
     */
    private function result(
        array $auto,
        array $profile,
        array $input,
        array $scoring,
        Collection $purchaseOffers,
        Collection $pawnOffers,
    ): array {
        $score = $scoring['score'];
        $dti = $scoring['dti'] * 100;
        $risk = $this->scoring->riskLevel($score, $scoring['dti']);
        $downPct = $input['precio'] > 0 ? ($input['enganche'] / $input['precio']) * 100 : 0;
        $brand = $auto['marca'] ?? '';
        $model = $auto['modelo'] ?? '';

        $summary = match ($risk) {
            'bajo' => 'Excelente perfil financiero. Tienes alta probabilidad de aprobación para este '.$brand.' '.$model.' con tasas preferenciales.',
            'medio' => 'Perfil viable. Calificas para el crédito de este '.$brand.' '.$model.', aunque algunas opciones solicitarán comprobantes adicionales o mayor enganche.',
            default => 'Perfil con observaciones. Te recomendamos aumentar el enganche o extender el plazo para reducir tu compromiso mensual.',
        };

        return [
            'scorePerfilEstimado' => $score,
            'nivelRiesgo' => $risk,
            'capacidadPagoMensualMaxima' => (int) max(0, round(($input['ingreso'] * 0.35) - $input['deudas'])),
            'dtiCalculadoPct' => round($dti, 1),
            'resumenEjecutivo' => $summary,
            'recomendaciones' => [
                $downPct < 20
                    ? 'Aumentar tu enganche al 20% reducirá tu mensualidad y mejorará la tasa ofrecida.'
                    : 'Mantén tu comprobación de ingresos al día con estados de cuenta en PDF.',
                'Evita solicitar múltiples créditos en la misma semana para no afectar tu score en Buró.',
                'Elige un plazo de 48 a 60 meses para balancear el costo total con una mensualidad cómoda.',
            ],
            'opcionesRecomendadas' => $this->mapOffers($purchaseOffers),
            'opcionesPrendarias' => $this->mapOffers($pawnOffers),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $offers
     * @return array<int, array<string, mixed>>
     */
    private function mapOffers(Collection $offers): array
    {
        return $offers->map(fn (array $offer) => [
            'id' => $offer['id'],
            'slug' => $offer['slug'],
            'nombre' => $offer['nombre'],
            'tipo' => $offer['tipo'],
            'logo' => $offer['logo'],
            'color' => $offer['color'],
            'tasa_min' => $offer['tasa_min'],
            'tasa_max' => $offer['tasa_max'],
            'cat_min' => $offer['cat_min'],
            'cat_max' => $offer['cat_max'],
            'enganche_pct' => $offer['enganche_pct'],
            'plazo_max' => $offer['plazo_max'],
            'score_min' => $offer['score_min'],
            'destacado' => $offer['destacado'],
            'aplica_para' => $offer['aplica_para'],
            'contacto' => $offer['contacto'],
            'notas' => $offer['notas'],
            'califica' => $offer['elegible'],
            'probabilidadAprobacion' => $offer['aprobacion'],
            'tasa' => $offer['tasa'],
            'tasaEstimadaAnual' => $offer['tasa'],
            'mensualidad' => $offer['mensualidad'],
            'mensualidadEstimada' => $offer['mensualidad'],
            'motivoDictamen' => $offer['motivo'],
            'requisitosClave' => $offer['requisitos'],
            'cat' => $offer['cat'],
            'total' => $offer['total'],
            'cargoServicio' => $offer['cargo_servicio'],
            'plazoEfectivo' => $offer['plazo_efectivo'],
            'plazoAjustado' => $offer['plazo_ajustado'],
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @param  Collection<int, array<string, mixed>>  $offers
     * @return array{resumenEjecutivo: string, recomendaciones: array<int, string>}|null
     */
    private function narrativeWithOpenAi(array $auto, array $profile, Collection $offers, string $apiKey): ?array
    {
        $context = $offers->take(15)->map(fn (array $offer) => [
            'nombre' => $offer['nombre'],
            'tipo' => $offer['tipo'],
            'califica' => $offer['elegible'],
            'tasa' => $offer['tasa'],
            'mensualidad' => $offer['mensualidad'],
            'motivo' => $offer['motivo'],
        ])->values()->all();

        $response = Http::withToken($apiKey)
            ->timeout(25)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un asesor de crédito automotriz en México. Redacta un resumen ejecutivo breve y 3 recomendaciones accionables para el cliente. NO calcules ni cambies tasas, CAT, elegibilidad, mensualidades o plazos: esos datos ya fueron calculados por el servidor. Devuelve SOLO JSON con resumenEjecutivo (string) y recomendaciones (array de strings).',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'auto' => $auto,
                            'perfil' => $profile,
                            'dictamenes' => $context,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        $response->throw();
        $content = (string) $response->json('choices.0.message.content', '{}');
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! is_string($decoded['resumenEjecutivo'] ?? null) || ! is_array($decoded['recomendaciones'] ?? null)) {
            return null;
        }

        return [
            'resumenEjecutivo' => $decoded['resumenEjecutivo'],
            'recomendaciones' => array_values(array_filter($decoded['recomendaciones'], 'is_string')),
        ];
    }

    /**
     * @return Collection<int, Financiera>
     */
    private function fallbackFinancieras(): Collection
    {
        return collect([
            ['slug' => 'bbva', 'nombre' => 'BBVA Auto', 'tipo' => 'banco', 'tasa_min' => 12.5, 'tasa_max' => 16.5, 'cat_min' => 14.2, 'cat_max' => 19.8, 'enganche_pct' => 10, 'plazo_max' => 72, 'score_min' => 620],
            ['slug' => 'santander', 'nombre' => 'Santander Auto', 'tipo' => 'banco', 'tasa_min' => 12.5, 'tasa_max' => 17.9, 'cat_min' => 15.1, 'cat_max' => 21.2, 'enganche_pct' => 15, 'plazo_max' => 72, 'score_min' => 620],
            ['slug' => 'banorte', 'nombre' => 'Banorte Auto', 'tipo' => 'banco', 'tasa_min' => 12.9, 'tasa_max' => 18.5, 'cat_min' => 15.8, 'cat_max' => 22.0, 'enganche_pct' => 10, 'plazo_max' => 60, 'score_min' => 600],
            ['slug' => 'hsbc', 'nombre' => 'HSBC Auto', 'tipo' => 'banco', 'tasa_min' => 13.9, 'tasa_max' => 18.9, 'cat_min' => 16.5, 'cat_max' => 23.0, 'enganche_pct' => 10, 'plazo_max' => 60, 'score_min' => 620],
            ['slug' => 'afirme', 'nombre' => 'Afirme Auto', 'tipo' => 'banco', 'tasa_min' => 14.9, 'tasa_max' => 22.5, 'cat_min' => 18.0, 'cat_max' => 25.0, 'enganche_pct' => 10, 'plazo_max' => 72, 'score_min' => 580],
        ])->map(function (array $data) {
            $financiera = new Financiera($data);
            $financiera->forceFill([
                'id' => (string) Str::uuid(),
                'vehicle_condition' => 'any',
                'brand_scope' => null,
                'max_vehicle_age' => 8,
                'requires_membership' => false,
            ]);

            return $financiera;
        });
    }
}
