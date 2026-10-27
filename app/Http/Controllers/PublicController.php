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
use App\Services\Pricing\CarPriceSuggestionService;
use App\Services\PublicationPlanService;
use App\Support\AgencyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $brands = CarBrand::query()
            ->withCount(['models'])
            ->orderBy('name')
            ->limit(16)
            ->get(['id', 'name', 'slug', 'logo']);

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->whereIn('audience', ['user', 'both'])
            ->orderBy('sort_order')
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
            '$brands' => $$brands,
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
            'canonical' => route('comprar.city', $city),
            'noindex' => false,
        ]);
    }

    public function bodyLanding(Request $request, string $body): Response
    {
        $body = urldecode($body);

        return $this->catalogResponse($request, ['body' => $body], [
            'title' => 'Autos '.$body.' seminuevos en México — Wecar',
            'description' => 'Explora autos tipo '.$body.' seminuevos certificados con financiamiento y compra segura en Wecar.',
            'canonical' => route('comprar.body', $body),
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

    public function autoShow(string $car, CarPriceSuggestionService $priceSuggestions): Response|RedirectResponse
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

        $priceReference = $model->model
            ? $priceSuggestions->comparables(
                $model->model,
                (int) $model->year,
                $model->version_id,
                (int) $model->km,
                $model->getKey(),
            )
            : ['samples' => 0, 'min' => null, 'p25' => null, 'median' => null, 'p75' => null, 'max' => null, 'confidence' => 'none', 'scope' => 'model'];

        $financing = [
            'min_rate' => (float) (Financiera::query()
                ->where('activo', true)
                ->whereNotNull('tasa_min')
                ->where('tasa_min', '>', 0)
                ->min('tasa_min') ?? 12.5),
            'max_term' => max(12, (int) (Financiera::query()
                ->where('activo', true)
                ->where('plazo_max', '>', 0)
                ->max('plazo_max') ?? 72)),
            'service_fee_rate' => 0.025,
        ];

        $indexable = $model->isSeoIndexable();
        $location = $model->city ?: $model->state;
        $fallbackTitle = trim(implode(' ', array_filter([
            $model->brand?->name,
            $model->model?->name,
            $model->version?->name,
            $model->year,
        ])));

        if ($location) {
            $fallbackTitle .= ' en '.$location;
        }

        $fallbackTitle = Str::limit(trim($fallbackTitle).' | Wecar', 72, '');
        $descriptionSuffix = trim(implode(' · ', array_filter([
            $location,
            number_format((int) $model->km, 0, '.', ',').' km',
            number_format((float) $model->price, 0, '.', ',').' MXN',
        ])));

        $description = $model->seo_description
            ?: trim(Str::limit(strip_tags((string) $model->description), 120).' '.$descriptionSuffix);

        return Inertia::render('Public/AutoDetalle', [
            'car' => (new CarResource($model))->resolve(request()),
            'similar' => CarResource::collection($similar)->resolve(request()),
            'priceReference' => $priceReference,
            'financing' => $financing,
            'seo' => [
                'title' => $model->seo_title ?: $fallbackTitle,
                'description' => Str::limit($description ?: 'Auto seminuevo certificado en Wecar.', 160, ''),
                'canonical' => route('autos.show', $model->slug),
                'image' => $this->absoluteImage($model->image ?? data_get($model->gallery, 0)),
                'images' => collect($model->gallery ?? [])
                    ->map(fn (string $image) => $this->absoluteImage($image))
                    ->filter()
                    ->values()
                    ->all(),
                'noindex' => ! $indexable,
                'robots' => $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow',
                'availability' => $model->status?->name === 'vendido'
                    ? 'https://schema.org/SoldOut'
                    : 'https://schema.org/InStock',
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

    public function vende(Request $request, PublicationPlanService $publicationPlans): Response
    {
        $user = $request->user();
        $agency = $user ? app(AgencyContext::class)->current($request) : null;
        $agency = $agency && $user?->belongsToAgency($agency) ? $agency : null;

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->whereIn('audience', $agency ? ['agency', 'both'] : ['user', 'both'])
            ->orderBy('sort_order')
            ->get();

        $activeSubscription = $user && ! $agency ? $publicationPlans->activeForUser($user) : null;
        $activePlan = $activeSubscription ? [
            'name' => $publicationPlans->planFor($activeSubscription)?->name ?? data_get($activeSubscription->metadata, 'plan_name'),
            'key' => $publicationPlans->planFor($activeSubscription)?->key_name,
            'ends_at' => $activeSubscription->ends_at?->toIso8601String(),
            'max_cars' => $publicationPlans->maxCars($activeSubscription),
            'used_cars' => $publicationPlans->activeCarsCount($user),
        ] : null;

        $agencyPlan = $agency ? $publicationPlans->planForAgency($agency) : null;
        $agencyPlanData = $agency ? [
            'name' => $agencyPlan?->name ?? ucfirst($agency->plan),
            'ends_at' => $agency->plan_expires_at?->toIso8601String(),
            'max_cars' => max((int) $agency->max_cars, 1),
            'used_cars' => $publicationPlans->activeAgencyCarsCount($agency),
            'active' => $agency->isPlanActive(),
        ] : null;

        return Inertia::render('Public/Vende', [
            'brands' => CarBrand::query()->with('models:id,brand_id,name')->orderBy('name')->get(['id', 'name', 'slug']),
            'plans' => $plans,
            'activePlan' => $activePlan,
            'agencyPlan' => $agencyPlanData,
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

    private function absoluteImage(?string $image): ?string
    {
        if (! filled($image)) {
            return null;
        }

        $base = rtrim((string) config('seo.base_url', config('app.url')), '/');

        if (Str::startsWith($image, ['http://', 'https://'])) {
            $host = parse_url($image, PHP_URL_HOST);
            $path = parse_url($image, PHP_URL_PATH) ?: '/';

            $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

            if ($host === 'localhost' || $host === '127.0.0.1' || ($isIp && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
                return $base.'/'.ltrim($path, '/');
            }

            return $image;
        }

        return $base.'/'.ltrim($image, '/');
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
