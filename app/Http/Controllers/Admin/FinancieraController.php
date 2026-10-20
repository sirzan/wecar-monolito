<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financiera;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancieraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Financieras', [
            'financieras' => Financiera::query()->orderBy('orden')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Financiera::query()->create($this->validated($request));

        return back()->with('success', 'Financiera creada.');
    }

    public function update(Request $request, Financiera $financiera): RedirectResponse
    {
        $financiera->update($this->validated($request, $financiera));

        return back()->with('success', 'Financiera actualizada.');
    }

    public function destroy(Financiera $financiera): RedirectResponse
    {
        $financiera->delete();

        return back()->with('success', 'Financiera eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Financiera $financiera = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('financieras', 'slug')->ignore($financiera?->getKey())],
            'tipo' => ['required', 'in:banco,financiera_marca,autofinanciamiento,prendaria,fintech'],
            'logo' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'tasa_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tasa_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cat_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cat_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'enganche_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'plazo_max' => ['nullable', 'integer', 'min:1', 'max:120'],
            'score_min' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'destacado' => ['boolean'],
            'aplica_para' => ['nullable', 'string', 'max:200'],
            'contacto' => ['nullable', 'string', 'max:200'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);

        $validated['slug'] = empty($validated['slug']) ? Str::slug($validated['nombre']) : $validated['slug'];
        $validated['orden'] = $validated['orden'] ?? 0;

        return $validated;
    }
}
