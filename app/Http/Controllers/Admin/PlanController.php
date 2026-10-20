<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans', [
            'plans' => Plan::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::query()->create($this->validated($request));

        return back()->with('success', 'Plan creado.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return back()->with('success', 'Plan actualizado.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('success', 'Plan eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'key_name' => ['required', 'string', 'max:60', Rule::unique('plans', 'key_name')->ignore($plan?->getKey())],
            'type' => ['nullable', Rule::in(['publication', 'membership', 'tokens'])],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'string', 'max:60'],
            'duration_weeks' => ['required', 'integer', 'min:0', 'max:520'],
            'max_cars' => ['required', 'integer', 'min:0', 'max:10000'],
            'token_amount' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'highlight' => ['boolean'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['type'] = $validated['type'] ?? 'publication';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['token_amount'] = $validated['type'] === 'tokens' ? ($validated['token_amount'] ?? null) : null;

        return $validated;
    }
}
