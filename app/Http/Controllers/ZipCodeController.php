<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ZipCodeController extends Controller
{
    public function __invoke(Request $request, string $cp): JsonResponse
    {
        $validated = validator(['cp' => $cp], [
            'cp' => ['required', 'regex:/^\d{5}$/'],
        ])->validate();

        $places = Cache::remember('zip-code:'.$validated['cp'], now()->addDay(), function () use ($validated): array {
            $base = rtrim((string) config('services.zip_code.url', 'https://api.zippopotam.us/mx'), '/');

            try {
                $response = Http::timeout(6)
                    ->acceptJson()
                    ->get($base.'/'.$validated['cp']);

                if ($response->status() === 404) {
                    return [];
                }

                $response->throw();

                return collect($response->json('places', []))
                    ->map(fn (array $place) => [
                        'colonia' => Str::title(trim((string) ($place['place name'] ?? ''))),
                        'municipio' => '',
                        'estado' => Str::title(trim((string) ($place['state'] ?? ''))),
                    ])
                    ->filter(fn (array $place) => $place['colonia'] !== '')
                    ->unique('colonia')
                    ->values()
                    ->all();
            } catch (\Throwable $exception) {
                report($exception);

                return [];
            }
        });

        if ($places === []) {
            return response()->json([
                'places' => [],
                'error' => 'No pudimos consultar ese código postal. Captura tu colonia manualmente.',
            ], 422);
        }

        return response()->json(['places' => $places]);
    }
}
