<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\CarStatus;
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

    public function updateStatus(Request $request, Car $car): RedirectResponse
    {
        $this->authorizeCar($request, $car);

        $validated = $request->validate([
            'status_id' => ['required', 'uuid', 'exists:car_statuses,id'],
        ]);

        $car->update(['status_id' => $validated['status_id']]);

        return back()->with('success', 'Estatus actualizado.');
    }

    public function destroy(Request $request, Car $car): RedirectResponse
    {
        $this->authorizeCar($request, $car);

        $car->delete();

        return back()->with('success', 'Publicación eliminada.');
    }

    private function authorizeCar(Request $request, Car $car): void
    {
        $user = $request->user();

        if ($car->owner_id === $user->getKey()) {
            return;
        }

        if ($car->agency_id && $car->agency && in_array($user->agencyRole($car->agency), ['owner', 'admin'], true)) {
            return;
        }

        abort(403);
    }
}
