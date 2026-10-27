<?php

namespace App\Services\Pricing;

use App\Models\Car;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Builder;

class CarPriceSuggestionService
{
    private const MIN_SANE_PRICE = 30_000;

    private const MAX_SANE_PRICE = 15_000_000;

    private const MIN_SAMPLES_FOR_VERSION = 3;

    /**
     * @return array<string, mixed>
     */
    public function comparables(
        CarModel $model,
        int $year,
        ?string $versionId = null,
        ?int $km = null,
        ?string $excludeCarId = null,
    ): array {
        $query = $this->baseQuery($model, $year, $excludeCarId);

        if ($versionId) {
            $prices = (clone $query)
                ->where('version_id', $versionId)
                ->pluck('price')
                ->map(fn ($price) => (float) $price)
                ->all();

            if (count($prices) >= self::MIN_SAMPLES_FOR_VERSION) {
                return $this->stats($prices, 'version', $km);
            }
        }

        $prices = $query
            ->pluck('price')
            ->map(fn ($price) => (float) $price)
            ->all();

        return $this->stats($prices, 'model', $km);
    }

    private function baseQuery(CarModel $model, int $year, ?string $excludeCarId): Builder
    {
        return Car::query()
            ->published()
            ->where('model_id', $model->getKey())
            ->whereBetween('year', [$year - 2, $year + 2])
            ->whereBetween('price', [self::MIN_SANE_PRICE, self::MAX_SANE_PRICE])
            ->whereHas('status', fn (Builder $query) => $query->whereIn('name', ['en_venta', 'promocion', 'nuevo']))
            ->when($excludeCarId, fn (Builder $query, string $id) => $query->whereKeyNot($id));
    }

    /**
     * @param  array<int, float>  $prices
     * @return array<string, mixed>
     */
    private function stats(array $prices, string $scope, ?int $km): array
    {
        $prices = $this->removeOutliers(array_values(array_filter(
            $prices,
            fn (float $price) => $price >= self::MIN_SANE_PRICE && $price <= self::MAX_SANE_PRICE,
        )));

        $samples = count($prices);

        if ($samples === 0) {
            return [
                'samples' => 0,
                'min' => null,
                'p25' => null,
                'median' => null,
                'p75' => null,
                'max' => null,
                'confidence' => 'none',
                'scope' => $scope,
            ];
        }

        sort($prices);

        $min = (int) round($prices[0]);
        $p25 = (int) round($this->percentile($prices, 25));
        $median = (int) round($this->median($prices));
        $p75 = (int) round($this->percentile($prices, 75));
        $max = (int) round($prices[$samples - 1]);
        $spread = $median > 0 ? ($p75 - $p25) / $median : 1;

        return [
            'samples' => $samples,
            'min' => $min,
            'p25' => $p25,
            'median' => $median,
            'p75' => $p75,
            'max' => $max,
            'confidence' => $this->confidence($samples, $spread),
            'scope' => $scope,
            'km' => $km,
        ];
    }

    private function confidence(int $samples, float $spread): string
    {
        if ($samples >= 5 && $spread <= 0.3) {
            return 'high';
        }

        if ($samples >= 3 && $spread <= 0.55) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<int, float>  $sorted
     * @return array<int, float>
     */
    private function removeOutliers(array $sorted): array
    {
        $sorted = array_values($sorted);
        sort($sorted);

        if (count($sorted) < 4) {
            return $sorted;
        }

        $q1 = $this->percentile($sorted, 25);
        $q3 = $this->percentile($sorted, 75);
        $iqr = $q3 - $q1;
        $low = $q1 - (1.5 * $iqr);
        $high = $q3 + (1.5 * $iqr);
        $filtered = array_values(array_filter($sorted, fn (float $price) => $price >= $low && $price <= $high));

        return $filtered !== [] ? $filtered : $sorted;
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private function percentile(array $sorted, float $percentile): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0;
        }

        if ($count === 1) {
            return (float) $sorted[0];
        }

        $index = ($percentile / 100) * ($count - 1);
        $low = (int) floor($index);
        $high = (int) ceil($index);

        if ($low === $high) {
            return (float) $sorted[$low];
        }

        return (float) ($sorted[$low] + (($sorted[$high] - $sorted[$low]) * ($index - $low)));
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private function median(array $sorted): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? (float) (($sorted[$middle - 1] + $sorted[$middle]) / 2)
            : (float) $sorted[$middle];
    }
}
