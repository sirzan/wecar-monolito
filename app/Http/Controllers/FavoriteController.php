<?php

namespace App\Http\Controllers;

use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $favorites = Favorite::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->get(['car_id']);

        $cars = Car::query()
            ->withTrashed()
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->whereIn('id', $favorites->pluck('car_id'))
            ->get()
            ->keyBy('id');

        $items = $favorites
            ->map(function (Favorite $favorite) use ($cars, $request): ?array {
                $car = $cars->get($favorite->car_id);

                if (! $car) {
                    return null;
                }

                $available = ! $car->trashed()
                    && $car->is_published
                    && ! in_array($car->status?->name, ['pendiente', 'pausado', 'cancelado', 'vendido'], true);

                return [
                    'car' => $available ? (new CarResource($car))->resolve($request) : null,
                    'id' => $car->getKey(),
                    'title' => trim(($car->brand?->name ?? '').' '.($car->model?->name ?? '').' '.$car->year),
                    'image' => $car->image ?? data_get($car->gallery, 0),
                    'available' => $available,
                    'url' => route('autos.show', $car->slug),
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('Panel/Favorites', [
            'items' => $items,
        ]);
    }

    public function ids(Request $request): JsonResponse
    {
        return response()->json([
            'ids' => Favorite::query()
                ->where('user_id', $request->user()->getKey())
                ->pluck('car_id')
                ->values(),
        ]);
    }

    public function toggle(Request $request, Car $car): JsonResponse
    {
        $favorite = Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->where('car_id', $car->getKey())
            ->first();

        if ($favorite) {
            $favorite->delete();
            $favorited = false;
        } else {
            Favorite::query()->firstOrCreate([
                'user_id' => $request->user()->getKey(),
                'car_id' => $car->getKey(),
            ]);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['uuid', 'exists:cars,id'],
        ]);

        $user = $request->user();
        $ids = collect($validated['ids'] ?? [])->unique()->values();

        foreach ($ids as $carId) {
            Favorite::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'car_id' => $carId,
            ]);
        }

        return response()->json([
            'ids' => Favorite::query()
                ->where('user_id', $user->getKey())
                ->pluck('car_id')
                ->values(),
        ]);
    }

    public function destroy(Request $request, Car $car): RedirectResponse
    {
        Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->where('car_id', $car->getKey())
            ->delete();

        return back()->with('success', 'Auto eliminado de favoritos.');
    }
}
