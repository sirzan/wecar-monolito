<?php

namespace App\Http\Controllers;

use App\Models\CarModel;
use App\Models\CarVersion;
use App\Services\Pricing\CarPriceSuggestionService;
use App\Services\Pricing\MarketPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarPriceSuggestionController extends Controller
{
    public function comparables(Request $request, CarPriceSuggestionService $service): JsonResponse
    {
        $validated = $request->validate([
            'model_id' => ['required', 'uuid', 'exists:car_models,id'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'version_id' => ['nullable', 'uuid', 'exists:car_versions,id'],
            'km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'exclude_car_id' => ['nullable', 'uuid', 'exists:cars,id'],
        ]);

        $model = CarModel::query()->findOrFail($validated['model_id']);

        $versionId = $validated['version_id'] ?? null;

        if ($versionId && ! CarVersion::query()->whereKey($versionId)->where('model_id', $model->getKey())->exists()) {
            $versionId = null;
        }

        return response()->json(
            $service->comparables(
                $model,
                (int) $validated['year'],
                $versionId,
                isset($validated['km']) ? (int) $validated['km'] : null,
                $validated['exclude_car_id'] ?? null,
            ),
        );
    }

    public function market(Request $request, MarketPriceService $service): JsonResponse
    {
        $validated = $request->validate([
            'model_id' => ['required', 'uuid', 'exists:car_models,id'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'version_id' => ['nullable', 'uuid', 'exists:car_versions,id'],
            'km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
        ]);

        $model = CarModel::query()->with('brand')->findOrFail($validated['model_id']);
        $versionName = null;

        if (! empty($validated['version_id'])) {
            $versionName = CarVersion::query()
                ->whereKey($validated['version_id'])
                ->where('model_id', $model->getKey())
                ->value('name');
        }

        return response()->json(
            $service->suggest(
                $model,
                (int) $validated['year'],
                $versionName,
                isset($validated['km']) ? (int) $validated['km'] : null,
            ),
        );
    }
}
