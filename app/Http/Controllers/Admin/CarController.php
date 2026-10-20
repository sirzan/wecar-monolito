<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Car;
use App\Models\CarStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CarController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'featured', 'agency_id']);

        $cars = Car::query()
            ->with(['brand', 'model', 'status', 'agency', 'owner'])
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $term = '%'.trim($q).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('slug', $term)
                        ->orWhereHas('brand', fn ($query) => $query->whereLike('name', $term))
                        ->orWhereHas('model', fn ($query) => $query->whereLike('name', $term))
                        ->orWhereHas('owner', fn ($query) => $query->whereLike('name', $term));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->whereRelation('status', 'name', $status))
            ->when(isset($filters['featured']) && $filters['featured'] !== '', fn ($query) => $query->where('featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['agency_id'] ?? null, fn ($query, string $agencyId) => $query->where('agency_id', $agencyId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Cars', [
            'cars' => $cars->through(fn (Car $car) => [
                'id' => $car->getKey(),
                'slug' => $car->slug,
                'title' => trim(($car->brand?->name ?? '').' '.($car->model?->name ?? '').' '.$car->year),
                'price' => (float) $car->price,
                'km' => (int) $car->km,
                'image' => $car->image,
                'status_id' => $car->status_id,
                'status' => $car->status?->label ?? $car->status?->name,
                'featured' => (bool) $car->featured,
                'is_published' => (bool) $car->is_published,
                'owner' => $car->owner?->name,
                'agency' => $car->agency?->name,
                'created_at' => $car->created_at?->toIso8601String(),
                'url' => route('autos.show', $car->slug),
            ]),
            'filters' => $filters,
            'statuses' => CarStatus::query()->orderBy('sort_order')->get(['id', 'name', 'label']),
            'agencies' => Agency::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Car $car): RedirectResponse
    {
        $validated = $request->validate([
            'status_id' => ['sometimes', 'uuid', 'exists:car_statuses,id'],
            'featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $car->update($validated);

        return back()->with('success', 'Auto actualizado.');
    }

    public function destroy(Car $car): RedirectResponse
    {
        $car->delete();

        return back()->with('success', 'Auto eliminado.');
    }
}
