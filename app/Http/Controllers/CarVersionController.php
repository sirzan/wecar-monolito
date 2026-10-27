<?php

namespace App\Http\Controllers;

use App\Models\CarModel;
use App\Services\CarCatalog\CarVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarVersionController extends Controller
{
    public function __invoke(Request $request, CarVersionService $versions): JsonResponse
    {
        $validated = $request->validate([
            'model_id' => ['nullable', 'uuid', 'exists:car_models,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:car_brands,id'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
        ]);

        $model = null;

        if (! empty($validated['model_id'])) {
            $model = CarModel::query()->find($validated['model_id']);

            if ($model && ! empty($validated['brand_id']) && $model->brand_id !== $validated['brand_id']) {
                $model = null;
            }
        } elseif (! empty($validated['brand']) && ! empty($validated['model'])) {
            $model = CarModel::query()
                ->whereHas('brand', fn ($query) => $query->whereRaw('lower(name) = ?', [mb_strtolower($validated['brand'])]))
                ->whereRaw('lower(name) = ?', [mb_strtolower($validated['model'])])
                ->first();
        }

        if (! $model) {
            return response()->json([
                'versions' => [],
                'error' => 'No encontramos ese modelo en el catálogo.',
            ], 422);
        }

        return response()->json([
            'versions' => $versions->versionsFor($model, (int) $validated['year']),
        ]);
    }
}
