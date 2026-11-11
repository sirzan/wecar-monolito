<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalSearchCache;
use App\Services\ExternalSearch\ExternalSearchCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternalCarController extends Controller
{
    public function __construct(private readonly ExternalSearchCacheService $cache) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'platform' => ['nullable', 'string', 'max:120'],
        ]);

        $groups = ExternalSearchCache::query()
            ->where('updated_at', '>=', now()->subDays($this->cache->days()))
            ->when($filters['q'] ?? null, fn ($query, string $queryText) => $query->whereLike('query_text', '%'.trim($queryText).'%'))
            ->when(
                $filters['platform'] ?? null,
                fn ($query, string $platform) => $query->whereJsonContains('platforms_searched', $platform),
            )
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ExternalSearchCache $group): array => [
                'id' => $group->getKey(),
                'query_text' => $group->query_text,
                'platforms' => $group->platforms_searched ?? [],
                'updated_at' => $group->updated_at?->toIso8601String(),
                'cars' => $this->cache->adminCars($group),
            ]);

        return Inertia::render('Admin/ExternalAutos', [
            'groups' => $groups,
            'filters' => $filters,
            'platforms' => collect(config('external_search.platforms', []))
                ->map(fn (array $platform): array => [
                    'id' => $platform['id'],
                    'name' => $platform['name'],
                ])
                ->values(),
        ]);
    }

    public function destroy(string $externalId): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/i', $externalId) === 1, 404);

        $removed = $this->cache->deleteCars([$externalId]);

        return back()->with(
            $removed > 0 ? 'success' : 'error',
            $removed > 0 ? 'Auto externo eliminado.' : 'El auto externo ya no existe en el caché.',
        );
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'string', 'size:32', 'regex:/^[a-f0-9]+$/i'],
        ]);

        $removed = $this->cache->deleteCars($validated['ids']);

        return back()->with('success', sprintf('%d autos externos eliminados.', $removed));
    }

    public function destroyGroup(ExternalSearchCache $group): RedirectResponse
    {
        $this->cache->deleteGroup($group);

        return back()->with('success', 'Grupo de búsqueda eliminado.');
    }
}
