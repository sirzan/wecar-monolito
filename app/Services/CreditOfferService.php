<?php

namespace App\Services;

use App\Models\Financiera;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreditOfferService
{
    private const SERVICE_FEE_RATE = 0.025;

    public function __construct(private readonly CreditScoringService $scoring) {}

    /**
     * @param  array{precio: float|int, enganche: float|int, plazo?: int, car_year?: int, brand?: string|null, vehicle_condition?: string|null}  $input
     * @param  Collection<int, Financiera>  $financieras
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $input, int $score, Collection $financieras): array
    {
        $price = max((float) $input['precio'], 0);
        $downPayment = max((float) $input['enganche'], 0);
        $amount = max($price - $downPayment, 0);
        $requestedTerm = max((int) ($input['plazo'] ?? 48), 1);
        $downPct = $price > 0 ? ($downPayment / $price) * 100 : 0;
        $vehicleYear = isset($input['car_year']) ? (int) $input['car_year'] : null;
        $vehicleAge = $vehicleYear ? max(0, (int) now()->year - $vehicleYear) : null;
        $brand = filled($input['brand'] ?? null) ? Str::slug((string) $input['brand']) : null;
        $condition = $this->normalizeCondition($input['vehicle_condition'] ?? null, $vehicleAge);
        $normalized = min(max(($score - 500) / 350, 0), 1);
        $serviceFee = round($amount * self::SERVICE_FEE_RATE, 2);

        $offers = $financieras->map(function (Financiera $financiera) use (
            $amount,
            $requestedTerm,
            $downPct,
            $downPayment,
            $score,
            $normalized,
            $vehicleAge,
            $brand,
            $condition,
            $serviceFee,
        ) {
            $rateMin = (float) ($financiera->tasa_min ?? 14.5);
            $rateMax = (float) ($financiera->tasa_max ?? 18.5);
            $catMin = (float) ($financiera->cat_min ?? 0);
            $catMax = (float) ($financiera->cat_max ?? 0);

            $rate = round($rateMax - ($rateMax - $rateMin) * $normalized, 2);
            $cat = $catMax > 0
                ? round($catMax - ($catMax - $catMin) * $normalized, 2)
                : round($rate * 1.2, 2);

            $effectiveTerm = min($requestedTerm, max((int) ($financiera->plazo_max ?? $requestedTerm), 1));
            $monthly = $this->scoring->monthlyPayment($amount, $rate, $effectiveTerm);
            $total = round($downPayment + ($monthly * $effectiveTerm) + $serviceFee, 2);

            $approval = 50 + ($score - (int) ($financiera->score_min ?? 500)) * 0.4;

            if ($downPct < (float) ($financiera->enganche_pct ?? 10)) {
                $approval -= 25;
            }

            $eligible = true;
            $reason = null;
            $requirements = $this->requirements($financiera);

            if ($financiera->vehicle_condition !== 'any' && $condition !== 'any' && $financiera->vehicle_condition !== $condition) {
                $eligible = false;
                $reason = $financiera->vehicle_condition === 'new'
                    ? 'Esta institución solo financia autos nuevos.'
                    : 'Esta institución solo financia autos seminuevos.';
            } elseif ($financiera->brand_scope && $brand && ! in_array($brand, $financiera->brand_scope, true)) {
                $eligible = false;
                $reason = 'Esta financiera solo atiende marcas de su grupo.';
            } elseif ($vehicleAge !== null && $financiera->max_vehicle_age !== null && $vehicleAge > (int) $financiera->max_vehicle_age) {
                $eligible = false;
                $reason = 'El auto tiene '.$vehicleAge.' años y excede el máximo de '.$financiera->max_vehicle_age.' años de esta institución.';
            } elseif ($financiera->score_min !== null && $score < (int) $financiera->score_min) {
                $eligible = false;
                $reason = 'Score mínimo requerido: '.$financiera->score_min.'.';
            } elseif ($downPct < (float) ($financiera->enganche_pct ?? 10)) {
                $eligible = false;
                $reason = 'Enganche mínimo de '.$financiera->enganche_pct.'% requerido.';
            }

            if (! $eligible) {
                $approval = min($approval, 25);
            }

            return [
                'id' => $financiera->getKey(),
                'slug' => $financiera->slug,
                'nombre' => $financiera->nombre,
                'tipo' => $financiera->tipo,
                'logo' => $financiera->logo,
                'color' => $financiera->color,
                'tasa_min' => $rateMin,
                'tasa_max' => $rateMax,
                'cat_min' => $catMin,
                'cat_max' => $catMax,
                'enganche_pct' => (float) ($financiera->enganche_pct ?? 10),
                'plazo_max' => (int) ($financiera->plazo_max ?? $requestedTerm),
                'score_min' => $financiera->score_min,
                'destacado' => (bool) $financiera->destacado,
                'aplica_para' => $financiera->aplica_para,
                'contacto' => $financiera->contacto,
                'notas' => $financiera->notas,
                'tasa' => $rate,
                'cat' => $cat,
                'mensualidad' => $monthly,
                'total' => $total,
                'cargo_servicio' => $serviceFee,
                'aprobacion' => (int) max(5, min(98, round($approval))),
                'elegible' => $eligible,
                'motivo' => $reason,
                'plazo_efectivo' => $effectiveTerm,
                'plazo_ajustado' => $effectiveTerm !== $requestedTerm,
                'requisitos' => $requirements,
            ];
        });

        return $offers
            ->sort(function (array $a, array $b) {
                if ($a['elegible'] !== $b['elegible']) {
                    return $a['elegible'] ? -1 : 1;
                }

                if ($a['destacado'] !== $b['destacado']) {
                    return $a['destacado'] ? -1 : 1;
                }

                if ($a['aprobacion'] !== $b['aprobacion']) {
                    return $b['aprobacion'] <=> $a['aprobacion'];
                }

                return $a['mensualidad'] <=> $b['mensualidad'];
            })
            ->values()
            ->all();
    }

    private function normalizeCondition(?string $condition, ?int $vehicleAge): string
    {
        if ($condition === 'new' || $condition === 'nuevo') {
            return 'new';
        }

        if ($condition === 'used' || $condition === 'seminuevo') {
            return 'used';
        }

        if ($condition === 'any') {
            return 'any';
        }

        return $vehicleAge !== null && $vehicleAge <= 1 ? 'new' : 'used';
    }

    /**
     * @return array<int, string>
     */
    private function requirements(Financiera $financiera): array
    {
        $requirements = [
            'Identificación oficial (INE)',
            'Comprobante de domicilio reciente',
        ];

        if ($financiera->tipo === 'banco' || $financiera->tipo === 'fintech') {
            $requirements[] = 'Comprobante de ingresos o estados de cuenta (3 meses)';
        }

        if ($financiera->requires_membership) {
            $requirements[] = 'Membresía activa en la institución';
        }

        if ($financiera->tipo === 'prendaria') {
            $requirements[] = 'Documentos del auto y comprobante de propiedad';
        }

        return $requirements;
    }
}
