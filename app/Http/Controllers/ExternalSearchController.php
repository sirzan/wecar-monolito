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
            'brand_id' => ['required', 'uuid', 'exists:car_brands,id'],
            'model_id' => ['required', 'uuid', 'exists:car_models,id'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'version_id' => ['nullable', 'uuid', 'exists:car_versions,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $brand = CarBrand::query()->find($validated['brand_id']);
        $model = CarModel::query()->find($validated['model_id']);

        abort_unless(
            $brand !== null && $model !== null && $model->brand_id === $brand->getKey(),
            422,
            'El modelo no pertenece a la marca seleccionada.',
        );

        $versionName = null;

        if (! empty($validated['version_id'])) {
            $version = CarVersion::query()
                ->whereKey($validated['version_id'])
                ->where('model_id', $model->getKey())
                ->first();

            abort_unless($version !== null, 422, 'La versión no pertenece al modelo seleccionado.');
            $versionName = $version->name;
        }

        $query = trim(implode(' ', array_filter([
            $brand->name,
            $model->name,
            (string) $validated['year'],
            $versionName,
            isset($validated['city']) ? trim($validated['city']) : null,
        ])));

        $result = $this->external->search(
            $request->user(),
            $request->ip(),
            $query,
            (bool) ($validated['refresh'] ?? false),
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
