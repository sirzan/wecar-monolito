<?php

namespace App\Http\Controllers;

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
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        $result = $this->external->search($request->user(), $request->ip(), $validated['q']);

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
