<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'active']);

        $agencies = Agency::query()
            ->with('owner')
            ->withCount(['cars', 'members'])
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $term = '%'.trim($q).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('name', $term)
                        ->orWhereLike('slug', $term);
                });
            })
            ->when(isset($filters['active']) && $filters['active'] !== '', fn ($query) => $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Agencies', [
            'agencies' => $agencies->through(fn (Agency $agency) => [
                'id' => $agency->getKey(),
                'slug' => $agency->slug,
                'name' => $agency->name,
                'owner' => $agency->owner?->name,
                'owner_email' => $agency->owner?->email,
                'city' => $agency->city,
                'plan' => $agency->plan,
                'plan_expires_at' => $agency->plan_expires_at?->toIso8601String(),
                'max_cars' => $agency->max_cars,
                'cars_count' => $agency->cars_count,
                'members_count' => $agency->members_count,
                'is_active' => (bool) $agency->is_active,
                'created_at' => $agency->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:40'],
            'max_cars' => ['required', 'integer', 'min:1', 'max:10000'],
            'plan_expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ]);

        $agency->update($validated);

        return back()->with('success', 'Agencia actualizada.');
    }
}
