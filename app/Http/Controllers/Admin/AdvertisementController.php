<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdvertisementController extends Controller
{
    private const PLACEMENTS = ['home_hero', 'home_inline', 'search_inline', 'detail_sidebar', 'global_top'];

    private const STATUSES = ['draft', 'active', 'paused', 'archived'];

    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $ads = Advertisement::query()
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return Inertia::render('Admin/Advertisements', [
            'advertisements' => $ads,
            'filters' => ['status' => $status],
            'placements' => self::PLACEMENTS,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Advertisement::query()->create([
            ...$this->validated($request),
            'created_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Anuncio creado.');
    }

    public function update(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $advertisement->update($this->validated($request, $advertisement));

        return back()->with('success', 'Anuncio actualizado.');
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        $advertisement->delete();

        return back()->with('success', 'Anuncio eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Advertisement $advertisement = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('advertisements', 'slug')->ignore($advertisement?->getKey())],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'url', 'max:500'],
            'placement' => ['required', Rule::in(self::PLACEMENTS)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }
}
