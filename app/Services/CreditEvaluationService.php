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
        $cacheKey = 'credit-evaluation:'.md5(json_encode([$auto, $profile]));

        return Cache::remember($cacheKey, 3600, function () use ($auto, $profile) {
            $financieras = Financiera::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            if ($financieras->isEmpty()) {
                $financieras = $this->fallbackFinancieras();
            }

            $apiKey = config('services.openai.key');

            if ($apiKey) {
                try {
                    $ai = $this->evaluateWithOpenAi($auto, $profile, $financieras, (string) $apiKey);

                    if ($ai !== null) {
                        return $ai;
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return $this->fallbackEvaluation($auto, $profile, $financieras);
        });
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @param  Collection<int, Financiera>  $financieras
     * @return array<string, mixed>
     */
    private function fallbackEvaluation(array $auto, array $profile, Collection $financieras): array
    {
        $input = [
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
        ];

        $scoring = $this->scoring->calculate($input);
        $score = $scoring['score'];
        $dti = $scoring['dti'] * 100;
        $risk = $this->scoring->riskLevel($score, $scoring['dti']);
        $offers = $this->offers->generate($input, $score, $financieras);
        $downPct = $input['precio'] > 0 ? ($input['enganche'] / $input['precio']) * 100 : 0;

        $recommendations = [
            $downPct < 20
                ? 'Aumentar tu enganche al 20% reducirá tu mensualidad y mejorará la tasa ofrecida.'
                : 'Mantén tu comprobación de ingresos al día con estados de cuenta en PDF.',
            'Evita solicitar múltiples créditos en la misma semana para no afectar tu score en Buró.',
            'Elige un plazo de 48 a 60 meses para balancear el costo total con una mensualidad cómoda.',
        ];

        $summary = match ($risk) {
            'bajo' => 'Excelente perfil financiero. Tienes alta probabilidad de aprobación con tasas preferenciales.',
            'medio' => 'Perfil viable. Calificas para el crédito, aunque algunas opciones solicitarán comprobantes adicionales o mayor enganche.',
            default => 'Perfil con observaciones. Te recomendamos aumentar el enganche o extender el plazo para reducir tu compromiso mensual.',
        };

        return [
            'scorePerfilEstimado' => $score,
            'nivelRiesgo' => $risk,
            'capacidadPagoMensualMaxima' => (int) max(0, round(($input['ingreso'] * 0.35) - $input['deudas'])),
            'dtiCalculadoPct' => round($dti, 1),
            'resumenEjecutivo' => $summary,
            'recomendaciones' => $recommendations,
            'opcionesRecomendadas' => $this->mapOffers($offers, $input['empleo']),
        ];
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $profile
     * @param  Collection<int, Financiera>  $financieras
     * @return array<string, mixed>|null
     */
    private function evaluateWithOpenAi(array $auto, array $profile, Collection $financieras, string $apiKey): ?array
    {
        $institutions = $financieras->take(25)->map(fn (Financiera $financiera) => [
            'slug' => $financiera->slug,
            'nombre' => $financiera->nombre,
            'tipo' => $financiera->tipo,
            'tasaMin' => (float) $financiera->tasa_min,
            'tasaMax' => (float) $financiera->tasa_max,
            'catMin' => (float) $financiera->cat_min,
            'catMax' => (float) $financiera->cat_max,
            'engancheMinPct' => (int) $financiera->enganche_pct,
            'plazoMaxMeses' => (int) $financiera->plazo_max,
            'scoreMinBuro' => $financiera->score_min,
            'aplicaPara' => $financiera->aplica_para,
            'notas' => $financiera->notas,
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
                        'content' => 'Eres un Director de Riesgos y Crédito Automotriz experto en México. Evalúa la solicitud con políticas bancarias reales: antigüedad máxima del auto, DTI máximo 35-40%, score de Buró y comprobación de ingresos. Devuelve SOLO JSON con: scorePerfilEstimado (300-850), nivelRiesgo (bajo|medio|alto), capacidadPagoMensualMaxima, dtiCalculadoPct, resumenEjecutivo, recomendaciones (3-5), opcionesRecomendadas (slug, nombre, tipo, califica, probabilidadAprobacion 0-100, tasaEstimadaAnual, mensualidadEstimada, motivoDictamen, requisitosClave).',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'auto' => $auto,
                            'perfil' => $profile,
                            'institucionesDisponibles' => $institutions,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        $response->throw();
        $content = (string) $response->json('choices.0.message.content', '{}');
        $decoded = json_decode($content, true);

        if (! is_array($decoded) || ! isset($decoded['opcionesRecomendadas'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<int, array<string, mixed>>  $offers
     * @return array<int, array<string, mixed>>
     */
    private function mapOffers(array $offers, string $employment): array
    {
        return collect($offers)->map(fn (array $offer) => [
            'slug' => $offer['slug'],
            'nombre' => $offer['nombre'],
            'tipo' => $offer['tipo'],
            'califica' => $offer['elegible'],
            'probabilidadAprobacion' => $offer['aprobacion'],
            'tasaEstimadaAnual' => $offer['tasa'],
            'mensualidadEstimada' => $offer['mensualidad'],
            'motivoDictamen' => $offer['motivo'] ?? ($offer['elegible']
                ? 'Cumples con el perfil crediticio e historial estimado.'
                : 'No cumples con la política de esta institución.'),
            'requisitosClave' => [
                $employment === 'fijo' ? '3 últimos recibos de nómina' : 'Estados de cuenta (3 meses)',
                'Identificación oficial (INE)',
                'Comprobante de domicilio reciente',
            ],
        ])->values()->all();
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
            $financiera->forceFill(['id' => (string) Str::uuid()]);

            return $financiera;
        });
    }
}
