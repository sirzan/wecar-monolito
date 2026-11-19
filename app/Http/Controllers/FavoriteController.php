<?php

namespace App\Http\Controllers;

use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\ExternalListing;
use App\Models\Favorite;
use App\Models\User;
use App\Support\ExternalListingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->get();

        $cars = Car::query()
            ->withTrashed()
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->whereIn('id', $favorites->pluck('car_id')->filter())
            ->get()
            ->keyBy('id');

        $listings = ExternalListing::query()
            ->whereIn('id', $favorites->pluck('external_listing_id')->filter())
            ->get()
            ->keyBy('id');

        $items = $favorites
            ->map(function (Favorite $favorite) use ($cars, $listings, $request): ?array {
                if ($favorite->external_listing_id) {
                    $listing = $listings->get($favorite->external_listing_id);

                    if (! $listing) {
                        return null;
                    }

                    return [
                        'type' => 'external',
                        'car' => null,
                        'external' => ExternalListingPresenter::card($listing),
                        'id' => $listing->external_id,
                        'title' => $listing->title,
                        'image' => collect($listing->images ?? [])->first(),
                        'available' => true,
                        'url' => route('external.detail', $listing->slug),
                    ];
                }

                $car = $cars->get($favorite->car_id);

                if (! $car) {
                    return null;
                }

                $available = ! $car->trashed()
                    && $car->is_published
                    && ! in_array($car->status?->name, ['pendiente', 'pausado', 'cancelado', 'vendido'], true);

                return [
                    'type' => 'car',
                    'car' => $available ? (new CarResource($car))->resolve($request) : null,
                    'external' => null,
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
                ->whereNotNull('car_id')
                ->pluck('car_id')
                ->values(),
            'externalIds' => $this->externalIds($request->user()),
        ]);
    }

    private function externalIds(User $user): Collection
    {
        $ids = Favorite::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('external_listing_id')
            ->pluck('external_listing_id');

        return ExternalListing::query()
            ->whereIn('id', $ids)
            ->pluck('external_id')
            ->values();
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

    public function toggleExternal(Request $request, ExternalListing $listing): JsonResponse
    {
        $favorite = Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->where('external_listing_id', $listing->getKey())
            ->first();

        if ($favorite) {
            $favorite->delete();
            $favorited = false;
        } else {
            Favorite::query()->firstOrCreate([
                'user_id' => $request->user()->getKey(),
                'external_listing_id' => $listing->getKey(),
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
            'external_ids' => ['nullable', 'array', 'max:100'],
            'external_ids.*' => ['string', 'exists:external_listings,external_id'],
        ]);

        $user = $request->user();
        $ids = collect($validated['ids'] ?? [])->unique()->values();
        $externalIds = collect($validated['external_ids'] ?? [])->unique()->values();

        foreach ($ids as $carId) {
            Favorite::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'car_id' => $carId,
            ]);
        }

        foreach ($externalIds as $externalId) {
            $listingId = ExternalListing::query()->where('external_id', $externalId)->value('id');

            if ($listingId) {
                Favorite::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'external_listing_id' => $listingId,
                ]);
            }
        }

        return response()->json([
            'ids' => Favorite::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('car_id')
                ->pluck('car_id')
                ->values(),
            'externalIds' => $this->externalIds($user),
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
