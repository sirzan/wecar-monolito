<?php

namespace App\Http\Controllers;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarVersion;
use App\Services\ExternalSearch\ExternalSearchService;
use App\Services\SearchTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalSearchController extends Controller
{
    public function __construct(
        private readonly ExternalSearchService $external,
        private readonly SearchTokenService $tokens,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->external->status($request->user(), $request->ip()));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:140', 'required_without:brand_id'],
            'brand_id' => ['nullable', 'uuid', 'exists:car_brands,id', 'required_without:q'],
            'model_id' => ['nullable', 'uuid', 'exists:car_models,id'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'version_id' => ['nullable', 'uuid', 'exists:car_versions,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $brand = ! empty($validated['brand_id'])
            ? CarBrand::query()->find($validated['brand_id'])
            : null;
        $model = ! empty($validated['model_id'])
            ? CarModel::query()->find($validated['model_id'])
            : null;

        if ($model !== null) {
            if ($brand !== null) {
                abort_unless(
                    $model->brand_id === $brand->getKey(),
                    422,
                    'El modelo no pertenece a la marca seleccionada.',
                );
            } else {
                $brand = CarBrand::query()->find($model->brand_id);
            }
        }

        $versionName = null;

        if (! empty($validated['version_id'])) {
            abort_unless(
                $model !== null,
                422,
                'Selecciona un modelo para filtrar por versión.',
            );

            $version = CarVersion::query()
                ->whereKey($validated['version_id'])
                ->where('model_id', $model->getKey())
                ->first();

            abort_unless($version !== null, 422, 'La versión no pertenece al modelo seleccionado.');
            $versionName = $version->name;
        }

        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $city = isset($validated['city']) && trim($validated['city']) !== '' ? trim($validated['city']) : null;
        $freeQuery = isset($validated['q']) && trim($validated['q']) !== '' ? trim($validated['q']) : null;

        $structuredQuery = trim(implode(' ', array_filter([
            $brand?->name,
            $model?->name,
            $year !== null ? (string) $year : null,
            $versionName,
            $city,
        ])));

        $query = $structuredQuery !== '' ? $structuredQuery : (string) $freeQuery;

        abort_unless($query !== '', 422, 'Ingresa al menos una marca o texto de búsqueda.');

        $specs = array_filter([
            'brand' => $brand?->name,
            'model' => $model?->name,
            'year' => $year,
            'version' => $versionName,
            'city' => $city,
            'q' => $structuredQuery === '' ? $freeQuery : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $result = $this->external->search(
            $request->user(),
            $request->ip(),
            $query,
            (bool) ($validated['refresh'] ?? false),
            $specs,
        );

        if (($result['error'] ?? null) === 'Búsqueda externa no disponible (API Key faltante).') {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function cached(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        return response()->json(
            $this->external->cached($request->user(), (int) ($validated['limit'] ?? 24)),
        );
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        return response()->json(
            $this->external->resolve($request->user(), $request->ip(), $validated['id']),
        );
    }

    public function rewardStart(Request $request): JsonResponse
    {
        $view = $this->tokens->startReward($request->user());

        return response()->json(['viewId' => $view->getKey()]);
    }

    public function rewardClaim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'view_id' => ['required', 'uuid'],
        ]);

        $balance = $this->tokens->claimReward($request->user(), $validated['view_id']);

        return response()->json(['balance' => $balance]);
    }
}
