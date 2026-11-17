<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarStatus;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $agencyIds = $user->accessibleAgencies()->pluck('id');

        $cars = Car::query()
            ->where(function ($query) use ($user, $agencyIds) {
                $query->where('owner_id', $user->getKey())
                    ->orWhereIn('agency_id', $agencyIds);
            })
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->latest()
            ->paginate(12);

        return Inertia::render('Panel/Publications', [
            'cars' => [
                'data' => CarResource::collection($cars->items())->resolve($request),
                'meta' => [
                    'current_page' => $cars->currentPage(),
                    'last_page' => $cars->lastPage(),
                    'total' => $cars->total(),
                ],
            ],
            'statuses' => CarStatus::query()->orderBy('sort_order')->get(['id', 'name', 'label']),
        ]);
    }

    public function edit(Request $request, Car $car): Response
    {
        $this->authorize('update', $car);

        $car->load(['brand', 'model', 'version', 'status', 'agency']);

        $data = (new CarResource($car))->resolve($request);
        $data['version_name'] = $car->version?->name;

        return Inertia::render('Public/Vende', [
            'editMode' => true,
            'car' => $data,
            'brands' => CarBrand::query()->with('models:id,brand_id,name')->orderBy('name')->get(['id', 'name', 'slug']),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->where('type', 'publication')
                ->orderBy('sort_order')
                ->get(),
            'activePlan' => null,
            'agencyPlan' => null,
            'filters' => [
                'bodies' => ['Sedán', 'SUV', 'Hatchback', 'Pickup', 'Coupé', 'Van'],
                'transmissions' => ['Automática', 'Manual', 'CVT'],
                'fuels' => ['Gasolina', 'Diésel', 'Híbrido', 'Eléctrico'],
                'colors' => ['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul', 'Verde'],
            ],
            'seo' => [
                'title' => 'Editar publicación — Wecar',
                'description' => 'Actualiza los datos de tu publicación en Wecar.',
                'canonical' => route('panel.publications.edit', $car->getKey()),
            ],
        ]);
    }

    public function updateStatus(Request $request, Car $car): RedirectResponse
    {
        $this->authorize('update', $car);

        $validated = $request->validate([
            'status_id' => ['required', 'uuid', 'exists:car_statuses,id'],
        ]);

        $car->update(['status_id' => $validated['status_id']]);

        return back()->with('success', 'Estatus actualizado.');
    }

    public function destroy(Request $request, Car $car): RedirectResponse
    {
        $this->authorize('delete', $car);

        $car->delete();

        return back()->with('success', 'Publicación eliminada.');
    }
}
