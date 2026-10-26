<?php

namespace App\Http\Controllers;

use App\Http\Resources\CarResource;
use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\Financiera;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Services\ExternalSearch\ExternalSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function __construct(private readonly ExternalSearchService $externalSearch) {}

    public function home(Request $request): Response
    {
        $featured = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $financieras = Financiera::query()
            ->where('activo', true)
            ->orderByDesc('destacado')
            ->orderBy('orden')
            ->limit(8)
            ->get();

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->orderBy('sort_order')
            ->limit(3)
            ->get(['id', 'key_name', 'name', 'price', 'duration', 'max_cars', 'highlight']);

        return Inertia::render('Public/Home', [
            'featuredCars' => CarResource::collection($featured)->resolve(request()),
            'externalCars' => $this->externalSearch->cached($request->user(), 8)['results'],
            'financieras' => $financieras,
            'plans' => $plans,
            'seo' => [
                'title' => 'Wecar — Compra y vende autos seminuevos en México',
                'description' => 'Wecar: compra, vende y financia autos seminuevos en México. Búsqueda inteligente, certificación de 160 puntos y compra segura garantizada.',
                'canonical' => route('home'),
            ],
        ]);
    }

    public function comprar(Request $request): Response
    {
        $filters = $request->only([
            'q', 'brand', 'city', 'body', 'transmission', 'fuel',
            'min_price', 'max_price', 'min_year', 'max_year', 'sort',
        ]);

        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();

        return $this->catalogResponse($request, $filters, [
            'title' => 'Comprar autos seminuevos en México — Wecar',
            'description' => 'Explora autos seminuevos certificados: filtra por marca, ciudad, precio, año y tipo de carrocería.',
            'canonical' => route('comprar'),
            'noindex' => $hasFilters,
        ]);
    }

    public function brandLanding(Request $request, CarBrand $brand): Response
    {
        return $this->catalogResponse($request, ['brand' => $brand->slug], [
            'title' => 'Autos '.$brand->name.' seminuevos en México — Wecar',
            'description' => 'Encuentra autos '.$brand->name.' seminuevos certificados, con financiamiento y compra segura en Wecar.',
            'canonical' => route('comprar.brand', $brand->slug),
            'noindex' => false,
        ]);
    }

    public function cityLanding(Request $request, string $city): Response
    {
        $city = urldecode($city);

        return $this->catalogResponse($request, ['city' => $city], [
            'title' => 'Autos seminuevos en '.$city.' — Wecar',
            'description' => 'Compra y vende autos seminuevos en '.$city.'. Catálogo certificado con financiamiento y compra segura.',
            'canonical' => route('comprar.city', rawurlencode($city)),
            'noindex' => false,
        ]);
    }

    public function bodyLanding(Request $request, string $body): Response
    {
        $body = urldecode($body);

        return $this->catalogResponse($request, ['body' => $body], [
            'title' => 'Autos '.$body.' seminuevos en México — Wecar',
            'description' => 'Explora autos tipo '.$body.' seminuevos certificados con financiamiento y compra segura en Wecar.',
            'canonical' => route('comprar.body', rawurlencode($body)),
            'noindex' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $seo
     */
    private function catalogResponse(Request $request, array $filters, array $seo): Response
    {
        $query = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status', 'agency']);

        $query->when($filters['q'] ?? null, function (Builder $query, string $q) {
            $term = '%'.trim($q).'%';
            $query->where(function (Builder $query) use ($term) {
                $query->whereLike('description', $term)
                    ->orWhereHas('brand', fn (Builder $query) => $query->whereLike('name', $term))
                    ->orWhereHas('model', fn (Builder $query) => $query->whereLike('name', $term));
            });
        });

        $query->when($filters['brand'] ?? null, fn (Builder $query, string $brand) => $query->whereRelation('brand', 'slug', $brand));
        $query->when($filters['city'] ?? null, fn (Builder $query, string $city) => $query->where('city', $city));
        $query->when($filters['body'] ?? null, fn (Builder $query, string $body) => $query->where('body', $body));
        $query->when($filters['transmission'] ?? null, fn (Builder $query, string $transmission) => $query->where('transmission', $transmission));
        $query->when($filters['fuel'] ?? null, fn (Builder $query, string $fuel) => $query->where('fuel', $fuel));
        $query->when($filters['min_price'] ?? null, fn (Builder $query, $min) => $query->where('price', '>=', (float) $min));
        $query->when($filters['max_price'] ?? null, fn (Builder $query, $max) => $query->where('price', '<=', (float) $max));
        $query->when($filters['min_year'] ?? null, fn (Builder $query, $min) => $query->where('year', '>=', (int) $min));
        $query->when($filters['max_year'] ?? null, fn (Builder $query, $max) => $query->where('year', '<=', (int) $max));

        match ($filters['sort'] ?? 'recent') {
            'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'),
            'year-desc' => $query->orderByDesc('year'),
            'km-asc' => $query->orderBy('km'),
            default => $query->orderByDesc('published_at')->orderByDesc('created_at'),
        };

        $cars = $query->paginate(24)->withQueryString();

        return Inertia::render('Public/Comprar', [
            'filters' => $filters,
            'cars' => [
                'data' => CarResource::collection($cars->items())->resolve($request),
                'meta' => [
                    'current_page' => $cars->currentPage(),
                    'last_page' => $cars->lastPage(),
                    'per_page' => $cars->perPage(),
                    'total' => $cars->total(),
                ],
            ],
            'options' => $this->catalogOptions(),
            'externalCars' => $this->externalSearch->cached($request->user(), 24)['results'],
            'seo' => $seo,
        ]);
    }

    public function externalShow(Request $request, string $id): Response
    {
        $cars = $this->externalSearch->cached($request->user(), 60)['results'];
        $car = collect($cars)->firstWhere('id', $id);

        abort_if(! $car, 404);

        return Inertia::render('Public/ExternalAutoDetalle', [
            'car' => $car,
            'seo' => [
                'title' => $car['title'].' — Wecar',
                'description' => 'Anuncio externo encontrado por la búsqueda inteligente de Wecar.',
                'canonical' => route('external.show', $id),
                'noindex' => true,
            ],
        ]);
    }

    public function autoShow(string $car): Response|RedirectResponse
    {
        $model = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status', 'agency', 'owner'])
            ->where('slug', $car)
            ->first()
            ?? Car::query()->published()->whereKey($car)->first();

        abort_if($model === null, 404);

        if ($model->slug !== $car) {
            return redirect()->route('autos.show', $model->slug, 301);
        }

        $similar = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->where('id', '!=', $model->getKey())
            ->where(function (Builder $query) use ($model) {
                $query->where('brand_id', $model->brand_id)
                    ->orWhere('body', $model->body);
            })
            ->orderByDesc('featured')
            ->limit(4)
            ->get();

        return Inertia::render('Public/AutoDetalle', [
            'car' => (new CarResource($model))->resolve(request()),
            'similar' => CarResource::collection($similar)->resolve(request()),
            'seo' => [
                'title' => trim(($model->brand?->name ?? '').' '.($model->model?->name ?? '').' '.$model->year).' — Wecar',
                'description' => $model->seo_description
                    ?? str($model->description ?? 'Auto seminuevo certificado en Wecar.')->limit(155)->toString(),
                'canonical' => route('autos.show', $model->slug),
                'image' => $model->image,
            ],
        ]);
    }

    public function comparar(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->take(3)
            ->values();

        $internalIds = $ids->reject(fn (string $id) => str_starts_with($id, 'ext-') || strlen($id) === 32);

        $cars = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status', 'agency'])
            ->whereIn('id', $internalIds)
            ->get()
            ->sortBy(fn (Car $car) => $ids->search($car->getKey()))
            ->values()
            ->map(fn (Car $car) => (new CarResource($car))->resolve($request));

        $externalCars = collect($this->externalSearch->cached($request->user(), 60)['results'])
            ->filter(fn (array $car) => $ids->contains($car['id']))
            ->map(fn (array $car) => [
                'id' => $car['id'],
                'slug' => 'externo-'.$car['id'],
                'title' => $car['title'],
                'year' => $car['year'] ?? 0,
                'km' => $car['km'] ?? 0,
                'price' => $car['price'] ?? 0,
                'image' => $car['imageUrl'],
                'transmission' => null,
                'fuel' => null,
                'body' => null,
                'color' => null,
                'city' => $car['location'],
                'url' => route('external.show', $car['id']),
                'agency' => null,
                'owner_name' => $car['platformName'] ?? 'Otra plataforma',
            ]);

        $compareCars = $cars->concat($externalCars)->sortBy(fn (array $car) => $ids->search($car['id']))->values();

        return Inertia::render('Public/Comparar', [
            'cars' => $compareCars,
            'seo' => [
                'title' => 'Comparar autos — Wecar',
                'description' => 'Compara hasta 3 autos seminuevos lado a lado: precio, kilometraje, año y equipamiento.',
                'canonical' => route('comparar'),
            ],
        ]);
    }

    public function vende(Request $request): Response
    {
        return Inertia::render('Public/Vende', [
            'brands' => CarBrand::query()->with('models:id,brand_id,name')->orderBy('name')->get(['id', 'name', 'slug']),
            'plans' => Plan::query()->where('is_active', true)->where('type', 'publication')->orderBy('sort_order')->get(),
            'filters' => [
                'bodies' => ['Sedán', 'SUV', 'Hatchback', 'Pickup', 'Coupé', 'Van'],
                'transmissions' => ['Automática', 'Manual', 'CVT'],
                'fuels' => ['Gasolina', 'Diésel', 'Híbrido', 'Eléctrico'],
                'colors' => ['Blanco', 'Negro', 'Gris', 'Plata', 'Rojo', 'Azul', 'Verde'],
            ],
            'seo' => [
                'title' => 'Vende tu auto en minutos — Wecar',
                'description' => 'Publica tu auto gratis, recibe ofertas de compradores reales y cobra seguro.',
                'canonical' => route('vende'),
            ],
        ]);
    }

    public function credito(): Response
    {
        return Inertia::render('Public/Credito', [
            'financieras' => Financiera::query()->where('activo', true)->orderBy('orden')->get(),
            'brands' => CarBrand::query()->with('models:id,brand_id,name')->orderBy('name')->get(['id', 'name', 'slug']),
            'seo' => [
                'title' => 'Crédito automotriz — Wecar',
                'description' => 'Simula tu crédito, compara financieras y compra con el mejor esquema de pago.',
                'canonical' => route('credito'),
            ],
        ]);
    }

    public function garantia(): Response
    {
        return Inertia::render('Public/Garantia', [
            'settings' => SiteSetting::get('warranty_config', []),
            'seo' => [
                'title' => 'Garantía Wecar',
                'description' => 'Validación mecánica y legal, acompañamiento y garantía extendida en cada compra.',
                'canonical' => route('garantia'),
            ],
        ]);
    }

    public function certificaciones(): Response
    {
        return Inertia::render('Public/Certificaciones', [
            'seo' => [
                'title' => 'Certificaciones Wecar',
                'description' => 'Inspección de 160 puntos mecánicos y legales para autos aprobados.',
                'canonical' => route('certificaciones'),
            ],
        ]);
    }

    public function nosotros(): Response
    {
        return Inertia::render('Public/Nosotros', [
            'settings' => SiteSetting::get('about_page', []),
            'seo' => [
                'title' => 'Nosotros — Wecar',
                'description' => 'Conoce la historia de Wecar y cómo transformamos la compra de autos en México.',
                'canonical' => route('nosotros'),
            ],
        ]);
    }

    public function contacto(): Response
    {
        return Inertia::render('Public/Contacto', [
            'settings' => SiteSetting::get('contact_info', []),
            'seo' => [
                'title' => 'Contacto — Wecar',
                'description' => 'Contáctanos por teléfono, correo o WhatsApp. Estamos para ayudarte.',
                'canonical' => route('contacto'),
            ],
        ]);
    }

    public function terminos(): Response
    {
        return Inertia::render('Public/Terminos', [
            'settings' => SiteSetting::get('terms_conditions', []),
            'seo' => [
                'title' => 'Términos y condiciones — Wecar',
                'description' => 'Términos y condiciones de uso de la plataforma Wecar.',
                'canonical' => route('terminos'),
            ],
        ]);
    }

    public function privacidad(): Response
    {
        return Inertia::render('Public/Privacidad', [
            'settings' => SiteSetting::get('privacy_policy', []),
            'seo' => [
                'title' => 'Aviso de privacidad — Wecar',
                'description' => 'Aviso de privacidad y tratamiento de datos personales de Wecar.',
                'canonical' => route('privacidad'),
            ],
        ]);
    }

    public function agencia(string $slug): Response
    {
        $agency = Agency::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $cars = Car::query()
            ->published()
            ->with(['brand', 'model', 'version', 'status'])
            ->where('agency_id', $agency->getKey())
            ->orderByDesc('published_at')
            ->paginate(24);

        return Inertia::render('Public/Agencia', [
            'agency' => $agency,
            'cars' => [
                'data' => CarResource::collection($cars->items())->resolve(request()),
                'meta' => [
                    'current_page' => $cars->currentPage(),
                    'last_page' => $cars->lastPage(),
                    'per_page' => $cars->perPage(),
                    'total' => $cars->total(),
                ],
            ],
            'seo' => [
                'title' => $agency->name.' — Agencia Wecar',
                'description' => $agency->description
                    ?? 'Autos seminuevos certificados de '.$agency->name.' en Wecar.',
                'canonical' => route('agencia.show', $agency->slug),
                'image' => $agency->logo,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogOptions(): array
    {
        $publishedCars = Car::query()->published();

        $brandCounts = (clone $publishedCars)
            ->selectRaw('brand_id, count(*) as total')
            ->whereNotNull('brand_id')
            ->groupBy('brand_id')
            ->pluck('total', 'brand_id');

        $brands = CarBrand::query()
            ->whereIn('id', $brandCounts->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (CarBrand $brand) => [
                'id' => $brand->getKey(),
                'name' => $brand->name,
                'slug' => $brand->slug,
                'count' => (int) $brandCounts[$brand->getKey()],
            ]);

        $cities = (clone $publishedCars)
            ->selectRaw('city, count(*) as total')
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => ['value' => $row->city, 'count' => (int) $row->total]);

        $bodies = (clone $publishedCars)
            ->selectRaw('body, count(*) as total')
            ->whereNotNull('body')
            ->groupBy('body')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['value' => $row->body, 'count' => (int) $row->total]);

        $transmissions = (clone $publishedCars)
            ->selectRaw('transmission, count(*) as total')
            ->whereNotNull('transmission')
            ->groupBy('transmission')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['value' => $row->transmission, 'count' => (int) $row->total]);

        $fuels = (clone $publishedCars)
            ->selectRaw('fuel, count(*) as total')
            ->whereNotNull('fuel')
            ->groupBy('fuel')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['value' => $row->fuel, 'count' => (int) $row->total]);

        $minPrice = (clone $publishedCars)->min('price') ?? 0;
        $maxPrice = (clone $publishedCars)->max('price') ?? 1000000;
        $minYear = (clone $publishedCars)->min('year') ?? 2000;
        $maxYear = (clone $publishedCars)->max('year') ?? (int) date('Y') + 1;

        return [
            'brands' => $brands,
            'cities' => $cities,
            'bodies' => $bodies,
            'transmissions' => $transmissions,
            'fuels' => $fuels,
            'price' => ['min' => (float) $minPrice, 'max' => (float) $maxPrice],
            'year' => ['min' => (int) $minYear, 'max' => (int) $maxYear],
        ];
    }
}
