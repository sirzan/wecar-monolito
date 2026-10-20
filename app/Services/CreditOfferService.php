<?php

namespace App\Services;

use App\Models\Financiera;
use Illuminate\Support\Collection;

class CreditOfferService
{
    public function __construct(private readonly CreditScoringService $scoring) {}

    /**
     * @param  array{precio: float|int, enganche: float|int, plazo?: int, car_year?: int}  $input
     * @param  Collection<int, Financiera>  $financieras
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $input, int $score, Collection $financieras): array
    {
        $price = max((float) $input['precio'], 0);
        $downPayment = max((float) $input['enganche'], 0);
        $amount = max($price - $downPayment, 0);
        $term = max((int) ($input['plazo'] ?? 48), 1);
        $downPct = $price > 0 ? ($downPayment / $price) * 100 : 0;
        $vehicleYear = (int) ($input['car_year'] ?? now()->year - 3);
        $vehicleAge = max(0, (int) now()->year - $vehicleYear);
        $normalized = min(max(($score - 500) / 350, 0), 1);

        $offers = $financieras->map(function (Financiera $financiera) use (
            $amount,
            $term,
            $downPct,
            $downPayment,
            $score,
            $normalized,
            $vehicleAge,
        ) {
            $rateMin = (float) ($financiera->tasa_min ?? 14.5);
            $rateMax = (float) ($financiera->tasa_max ?? 18.5);
            $catMin = (float) ($financiera->cat_min ?? $rateMin * 1.25);
            $catMax = (float) ($financiera->cat_max ?? $rateMax * 1.35);

            $rate = round($rateMax - ($rateMax - $rateMin) * $normalized, 2);
            $cat = round($catMax - ($catMax - $catMin) * $normalized, 2);
            $effectiveTerm = min($term, max((int) ($financiera->plazo_max ?? $term), 1));
            $monthly = $this->scoring->monthlyPayment($amount, $rate, $effectiveTerm);
            $total = round($monthly * $effectiveTerm + $downPayment, 2);

            $approval = 50 + ($score - (int) ($financiera->score_min ?? 500)) * 0.4;

            if ($downPct < (float) ($financiera->enganche_pct ?? 10)) {
                $approval -= 25;
            }

            $eligible = true;
            $reason = null;

            if ($financiera->score_min !== null && $score < ((int) $financiera->score_min - 40)) {
                $eligible = false;
                $reason = 'Score mínimo requerido: '.$financiera->score_min;
            } elseif ($downPct < (float) ($financiera->enganche_pct ?? 10)) {
                $eligible = false;
                $reason = 'Enganche mínimo de '.$financiera->enganche_pct.'% requerido';
            } elseif ($financiera->tipo === 'banco' && $vehicleAge > 8) {
                $eligible = false;
                $reason = 'El auto tiene más de 8 años y excede la política de antigüedad del banco.';
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
                'plazo_max' => (int) ($financiera->plazo_max ?? $term),
                'score_min' => $financiera->score_min,
                'destacado' => (bool) $financiera->destacado,
                'tasa' => $rate,
                'cat' => $cat,
                'mensualidad' => $monthly,
                'total' => $total,
                'aprobacion' => (int) max(5, min(98, round($approval))),
                'elegible' => $eligible,
                'motivo' => $reason,
                'plazo_efectivo' => $effectiveTerm,
            ];
        });

        return $offers
            ->sort(function (array $a, array $b) {
                if ($a['elegible'] !== $b['elegible']) {
                    return $a['elegible'] ? -1 : 1;
                }

                return $a['mensualidad'] <=> $b['mensualidad'];
            })
            ->values()
            ->all();
    }
}
