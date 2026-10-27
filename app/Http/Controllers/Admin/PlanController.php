<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        if ($plan->subscriptions()->exists() || $plan->agencies()->exists()) {
            $plan->update(['is_active' => false]);

            return back()->with('success', 'El plan tiene uso registrado; se desactivó en lugar de eliminarse.');
        }

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
            'audience' => ['nullable', Rule::in(['user', 'agency', 'both'])],
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
        $validated['audience'] = $validated['audience'] ?? 'user';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['token_amount'] = $validated['type'] === 'tokens' ? ($validated['token_amount'] ?? null) : null;

        if ($validated['type'] === 'publication' && $validated['duration_weeks'] < 1) {
            throw ValidationException::withMessages([
                'duration_weeks' => 'La duración debe ser de al menos una semana para planes de publicación.',
            ]);
        }

        if ($validated['type'] === 'publication' && $validated['max_cars'] < 1) {
            throw ValidationException::withMessages([
                'max_cars' => 'El plan debe permitir al menos un auto.',
            ]);
        }

        if ($validated['type'] === 'tokens' && ! $validated['token_amount']) {
            throw ValidationException::withMessages([
                'token_amount' => 'Indica cuántos tokens incluye el paquete.',
            ]);
        }

        if ($validated['audience'] !== 'user' && $validated['type'] !== 'publication') {
            throw ValidationException::withMessages([
                'audience' => 'Solo los planes de publicación pueden asignarse a agencias.',
            ]);
        }

        return $validated;
    }
}
