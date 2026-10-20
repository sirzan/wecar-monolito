<?php

namespace App\Services;

class CreditScoringService
{
    /**
     * @param  array{
     *     ingreso: float|int,
     *     edad?: int,
     *     antiguedad?: float|int,
     *     empleo?: string,
     *     historial?: string,
     *     deudas?: float|int,
     *     precio: float|int,
     *     enganche: float|int,
     *     plazo?: int
     * }  $input
     * @return array{score: int, dti: float, monthly_payment: float}
     */
    public function calculate(array $input): array
    {
        $income = max((float) ($input['ingreso'] ?? 0), 0);
        $price = max((float) ($input['precio'] ?? 0), 0);
        $downPayment = max((float) ($input['enganche'] ?? 0), 0);
        $term = max((int) ($input['plazo'] ?? 48), 1);
        $debts = max((float) ($input['deudas'] ?? 0), 0);
        $age = (int) ($input['edad'] ?? 0);
        $tenure = (float) ($input['antiguedad'] ?? 0);

        $score = 500;

        $historyMap = [
            'excelente' => 280,
            'bueno' => 200,
            'regular' => 110,
            'sin-historial' => 80,
            'malo' => -40,
        ];
        $score += $historyMap[$input['historial'] ?? 'bueno'] ?? 200;

        $amount = max($price - $downPayment, 0);
        $referenceRate = 0.15 / 12;
        $monthly = $amount > 0
            ? ($amount * $referenceRate) / (1 - pow(1 + $referenceRate, -$term))
            : 0;

        $dti = $income > 0 ? ($debts + $monthly) / $income : 1;

        if ($dti < 0.25) {
            $score += 80;
        } elseif ($dti < 0.35) {
            $score += 50;
        } elseif ($dti < 0.45) {
            $score += 10;
        } else {
            $score -= 60;
        }

        $employmentMap = [
            'fijo' => 50,
            'negocio' => 30,
            'honorarios' => 20,
            'eventual' => -20,
        ];
        $score += $employmentMap[$input['empleo'] ?? 'fijo'] ?? 50;

        if ($tenure >= 3) {
            $score += 30;
        } elseif ($tenure >= 1) {
            $score += 10;
        }

        if ($age >= 25 && $age <= 60) {
            $score += 20;
        }

        $downPct = $price > 0 ? $downPayment / $price : 0;

        if ($downPct >= 0.30) {
            $score += 40;
        } elseif ($downPct >= 0.20) {
            $score += 20;
        } elseif ($downPct < 0.10) {
            $score -= 20;
        }

        return [
            'score' => (int) max(300, min(850, round($score))),
            'dti' => round($dti, 4),
            'monthly_payment' => round($monthly, 2),
        ];
    }

    public function monthlyPayment(float $amount, float $annualRate, int $term): float
    {
        if ($amount <= 0) {
            return 0;
        }

        $rate = $annualRate / 100 / 12;

        if ($rate <= 0) {
            return round($amount / max($term, 1), 2);
        }

        return round(($amount * $rate) / (1 - pow(1 + $rate, -max($term, 1))), 2);
    }

    public function riskLevel(int $score, float $dti): string
    {
        return $dti < 0.35 && $score >= 650 ? 'bajo' : ($dti > 0.45 || $score < 560 ? 'alto' : 'medio');
    }
}
