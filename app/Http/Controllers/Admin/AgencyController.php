<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\PlanReminder;
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
            ->with(['owner', 'planRelation'])
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
                'plan_id' => $agency->plan_id,
                'plan_name' => $agency->planRelation?->name,
                'plan_expires_at' => $agency->plan_expires_at?->toIso8601String(),
                'max_cars' => $agency->max_cars,
                'cars_count' => $agency->cars_count,
                'members_count' => $agency->members_count,
                'is_active' => (bool) $agency->is_active,
                'created_at' => $agency->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'plans' => Plan::query()
                ->where('is_active', true)
                ->where('type', 'publication')
                ->where('is_active', true)
                ->whereIn('audience', ['agency', 'both'])
                ->orderBy('sort_order')
                ->get(['id', 'key_name', 'name', 'max_cars', 'duration_weeks']),
        ]);
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'uuid', 'exists:plans,id'],
            'max_cars' => ['required', 'integer', 'min:1', 'max:10000'],
            'plan_expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! empty($validated['plan_id'])) {
            $plan = Plan::query()
                ->whereKey($validated['plan_id'])
                ->where('type', 'publication')
                ->where('is_active', true)
                ->whereIn('audience', ['agency', 'both'])
                ->first();

            if (! $plan) {
                return back()->withErrors(['plan_id' => 'El plan seleccionado no es válido para agencias.']);
            }

            $validated['plan'] = $plan->key_name;
        }

        $agency->update($validated);

        if (array_key_exists('plan_expires_at', $validated) || array_key_exists('plan_id', $validated)) {
            PlanReminder::query()
                ->where('subject_type', Agency::class)
                ->where('subject_id', $agency->getKey())
                ->delete();
        }

        return back()->with('success', 'Agencia actualizada.');
    }
}
