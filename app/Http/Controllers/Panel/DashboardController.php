<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarLead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
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
            ->with(['brand', 'model', 'status'])
            ->latest()
            ->limit(6)
            ->get();

        $leads = CarLead::query()
            ->where('owner_id', $user->getKey())
            ->orWhereIn('agency_id', $agencyIds)
            ->with(['car.brand', 'car.model'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Panel/Dashboard', [
            'stats' => [
                'cars' => Car::query()->where('owner_id', $user->getKey())->count(),
                'active_cars' => Car::query()->where('owner_id', $user->getKey())->whereHas('status', fn ($query) => $query->where('name', 'en_venta'))->count(),
                'agency_cars' => Car::query()->whereIn('agency_id', $agencyIds)->count(),
                'leads' => CarLead::query()->whereIn('agency_id', $agencyIds)->count(),
            ],
            'agencies' => $user->accessibleAgencies()->map(fn ($agency) => [
                'id' => $agency->getKey(),
                'slug' => $agency->slug,
                'name' => $agency->name,
                'role' => $user->agencyRole($agency),
                'cars' => $agency->cars()->count(),
                'plan' => $agency->plan,
            ])->values(),
            'recentCars' => $cars->map(fn (Car $car) => [
                'id' => $car->getKey(),
                'slug' => $car->slug,
                'title' => trim(($car->brand?->name ?? '').' '.($car->model?->name ?? '').' '.$car->year),
                'price' => (float) $car->price,
                'status' => $car->status?->label ?? $car->status?->name,
            ])->values(),
            'recentLeads' => $leads->map(fn (CarLead $lead) => [
                'id' => $lead->getKey(),
                'contact_name' => $lead->contact_name,
                'contact_phone' => $lead->contact_phone,
                'status' => $lead->status,
                'car' => trim(($lead->car?->brand?->name ?? '').' '.($lead->car?->model?->name ?? '')),
                'created_at' => $lead->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function section(string $section): Response
    {
        $titles = [
            'favoritos' => 'Favoritos',
            'membresia' => 'Membresía Buscador',
            'tramites' => 'Mis trámites',
            'metodos-pago' => 'Métodos de pago',
            'soporte' => 'Soporte',
            'agencia' => 'Mi agencia',
        ];

        return Inertia::render('Panel/Placeholder', [
            'title' => $titles[$section] ?? 'Panel',
        ]);
    }
}
