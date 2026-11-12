<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Services\Pricing\CarPriceSuggestionService;
use App\Services\Pricing\MarketPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function priceModel(): CarModel
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);

    return CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
}

function priceCar(CarModel $model, int $price, int $year = 2022, string $status = 'en_venta', bool $published = true, string $slugSuffix = 'a'): Car
{
    $carStatus = CarStatus::query()->firstOrCreate(['name' => $status], ['label' => ucfirst($status)]);

    return Car::query()->create([
        'owner_id' => null,
        'brand_id' => $model->brand_id,
        'model_id' => $model->getKey(),
        'status_id' => $carStatus->getKey(),
        'slug' => 'price-car-'.$slugSuffix.'-'.str()->random(6),
        'year' => $year,
        'km' => 45000,
        'price' => $price,
        'city' => 'Monterrey, NL',
        'description' => 'Auto para prueba de precio.',
        'is_published' => $published,
        'published_at' => now(),
    ]);
}

it('calculates robust internal comparables and excludes invalid cars', function () {
    $model = priceModel();
    $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);
    CarStatus::query()->firstOrCreate(['name' => 'vendido'], ['label' => 'Vendido']);

    priceCar($model, 250000, 2022, 'en_venta', true, '1');
    priceCar($model, 280000, 2022, 'en_venta', true, '2');
    priceCar($model, 300000, 2022, 'en_venta', true, '3');
    priceCar($model, 320000, 2022, 'en_venta', true, '4');
    priceCar($model, 2000000, 2022, 'en_venta', true, 'outlier');
    priceCar($model, 100000, 2022, 'vendido', true, 'sold');
    priceCar($model, 90000, 2022, 'en_venta', false, 'draft');

    $result = app(CarPriceSuggestionService::class)->comparables($model, 2022);

    expect($result['samples'])->toBe(4)
        ->and($result['median'])->toBeGreaterThan(250000)
        ->and($result['max'])->toBeLessThan(2000000)
        ->and($result['scope'])->toBe('model');
});

it('returns comparables through the api', function () {
    $model = priceModel();
    priceCar($model, 250000, 2022);
    priceCar($model, 300000, 2022, 'en_venta', true, 'b');
    priceCar($model, 350000, 2022, 'en_venta', true, 'c');

    $this->getJson('/api/car-price-suggestions/comparables?model_id='.$model->getKey().'&year=2022')
        ->assertOk()
        ->assertJsonPath('samples', 3)
        ->assertJsonStructure(['min', 'p25', 'median', 'p75', 'max', 'confidence']);
});

it('fetches and caches market prices with robust parsing', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');
    $model = priceModel();

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => [
                'web' => [
                    ['url' => 'https://example.com/a', 'json' => ['precio_minimo' => '1.2 millones', 'precio_sugerido' => '1.3 millones', 'precio_maximo' => '1.4 millones']],
                    ['url' => 'https://example.com/b', 'json' => ['precio_minimo' => 1250000, 'precio_sugerido' => 1300000, 'precio_maximo' => 1350000]],
                    ['url' => 'https://example.com/c', 'json' => ['precio_minimo' => 1280000, 'precio_sugerido' => 1320000, 'precio_maximo' => 1380000]],
                ],
            ],
        ]),
    ]);

    $service = app(MarketPriceService::class);
    $first = $service->suggest($model, 2022, 'LE', 45000);
    $second = $service->suggest($model, 2022, 'LE', 45000);

    expect($first['suggested'])->toBeGreaterThan(0)
        ->and($first['suggested'])->toBeGreaterThan(1_000_000)
        ->and($first['currency'])->toBe('MXN')
        ->and($second['cached'])->toBeTrue();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => ($request['country'] ?? null) === 'MX'
        && ($request['location'] ?? null) === 'Mexico');
});

it('returns a friendly market error when firecrawl is not configured', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', null);

    $result = app(MarketPriceService::class)->suggest(priceModel(), 2022, 'LE', 45000);

    expect($result['suggested'])->toBeNull()
        ->and($result['error'])->toContain('FIRECRAWL_API_KEY');
});

it('prioritizes libro azul mxn buy and sell valuations matched by version', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');
    $model = priceModel();

    Http::fake(function (Request $request) {
        $domains = $request['includeDomains'] ?? [];

        if (in_array('libroazul.com', $domains, true)) {
            return Http::response([
                'data' => [
                    'web' => [
                        [
                            'url' => 'https://www.libroazul.com/toyota/corolla/2022',
                            'json' => [
                                'valuaciones' => [
                                    [
                                        'marca' => 'Toyota',
                                        'modelo' => 'Corolla',
                                        'anio' => 2022,
                                        'version' => 'LE CVT',
                                        'moneda' => 'MXN',
                                        'tipo_registro' => 'libro_azul',
                                        'precio_compra' => 285000,
                                        'precio_venta' => 325000,
                                        'precio_sugerido' => 315000,
                                    ],
                                    [
                                        'marca' => 'Toyota',
                                        'modelo' => 'Corolla',
                                        'anio' => 2022,
                                        'version' => 'SE',
                                        'moneda' => 'MXN',
                                        'tipo_registro' => 'libro_azul',
                                        'precio_compra' => 350000,
                                        'precio_venta' => 395000,
                                        'precio_sugerido' => 380000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([
            'data' => [
                'web' => [
                    [
                        'url' => 'https://www.kavak.com/mx/venta/toyota-corolla-le-2022',
                        'json' => [
                            'valuaciones' => [
                                [
                                    'marca' => 'Toyota',
                                    'modelo' => 'Corolla',
                                    'anio' => 2022,
                                    'version' => 'LE CVT',
                                    'moneda' => 'MXN',
                                    'tipo_registro' => 'seminuevo_venta',
                                    'precio_sugerido' => 345000,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    });

    $result = app(MarketPriceService::class)->suggest($model, 2022, 'LE CVT');

    expect($result['source_type'])->toBe('libro_azul')
        ->and($result['source_label'])->toBe('Libro Azul (Guía EBC)')
        ->and($result['match_level'])->toBe('version')
        ->and($result['currency'])->toBe('MXN')
        ->and($result['buy_price'])->toBe(285000)
        ->and($result['sell_price'])->toBe(325000)
        ->and($result['suggested'])->toBe(315000)
        ->and($result['sources'])->toContain('libroazul.com');
});

it('rejects non-mxn currencies, new cars, financing, wrong model or year, foreign domains and falls back with exact decimal parsing', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');
    $model = priceModel();

    Http::fake(function (Request $request) {
        $domains = $request['includeDomains'] ?? [];

        if (in_array('libroazul.com', $domains, true)) {
            return Http::response(['data' => ['web' => []]]);
        }

        return Http::response([
            'data' => [
                'web' => [
                    [
                        'url' => 'https://www.kavak.com/co/venta/toyota-corolla-2022',
                        'json' => [
                            'valuaciones' => [
                                [
                                    'modelo' => 'Corolla',
                                    'anio' => 2022,
                                    'moneda' => 'MXN',
                                    'tipo_registro' => 'seminuevo_venta',
                                    'precio_sugerido' => 95000000,
                                ],
                            ],
                        ],
                    ],
                    [
                        'url' => 'https://autos.mercadolibre.com.ar/toyota-corolla-2022',
                        'json' => [
                            'valuaciones' => [
                                [
                                    'modelo' => 'Corolla',
                                    'anio' => 2022,
                                    'moneda' => 'MXN',
                                    'tipo_registro' => 'seminuevo_venta',
                                    'precio_sugerido' => 2500000,
                                ],
                            ],
                        ],
                    ],
                    [
                        'url' => 'https://www.autocosmos.com.mx/autos-usados/toyota-corolla-2022',
                        'json' => [
                            'valuaciones' => [
                                ['modelo' => 'Corolla', 'anio' => 2022, 'moneda' => 'USD', 'tipo_registro' => 'seminuevo_venta', 'precio_sugerido' => 38000],
                                ['modelo' => 'Corolla', 'anio' => 2022, 'moneda' => 'OTRA', 'tipo_registro' => 'seminuevo_venta', 'precio_sugerido' => 890000],
                                ['modelo' => 'Corolla', 'anio' => 2022, 'moneda' => 'MXN', 'tipo_registro' => 'auto_nuevo', 'precio_sugerido' => 520000],
                                ['modelo' => 'Corolla', 'anio' => 2022, 'moneda' => 'MXN', 'tipo_registro' => 'financiamiento', 'precio_sugerido' => 45000],
                                ['modelo' => 'Corolla', 'anio' => 2019, 'moneda' => 'MXN', 'tipo_registro' => 'seminuevo_venta', 'precio_sugerido' => 210000],
                                ['modelo' => 'Yaris', 'anio' => 2022, 'moneda' => 'MXN', 'tipo_registro' => 'seminuevo_venta', 'precio_sugerido' => 235000],
                            ],
                        ],
                    ],
                    [
                        'url' => 'https://www.kavak.com/mx/venta/toyota-corolla-le-2022',
                        'json' => [
                            'valuaciones' => [
                                [
                                    'modelo' => 'Toyota Corolla',
                                    'anio' => 2022,
                                    'version' => 'LE',
                                    'moneda' => 'MXN',
                                    'tipo_registro' => 'seminuevo_venta',
                                    'precio_sugerido' => '$350,000.00 MXN',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    });

    $result = app(MarketPriceService::class)->suggest($model, 2022, 'LE');

    expect($result['source_type'])->toBe('market_mx')
        ->and($result['suggested'])->toBe(350000)
        ->and($result['samples'])->toBe(1)
        ->and($result['sources'])->toBe(['kavak.com']);
});

it('parses decimal prices cleanly in strict markdown fallback while ignoring usd and financing lines', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');
    $model = priceModel();

    Http::fake(function (Request $request) {
        $domains = $request['includeDomains'] ?? [];

        if (in_array('libroazul.com', $domains, true)) {
            return Http::response(['data' => ['web' => []]]);
        }

        return Http::response([
            'data' => [
                'web' => [
                    [
                        'url' => 'https://www.seminuevos.com/toyota-corolla-2015',
                        'markdown' => implode("\n", [
                            'Toyota Corolla 2015 seminuevo en Monterrey',
                            'Precio en USD: $18,500.00 USD',
                            'Enganche desde $45,000.00 mensualidad $4,200.00',
                            'Precio de contado MXN $85,000.00',
                        ]),
                    ],
                ],
            ],
        ]);
    });

    $result = app(MarketPriceService::class)->suggest($model, 2015);

    expect($result['source_type'])->toBe('market_mx')
        ->and($result['suggested'])->toBe(85000);
});

it('adjusts cached market prices by mileage without re-querying firecrawl and supports refresh through the api', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');
    $model = priceModel();
    $year = (int) date('Y') - 4;

    Http::fake(function (Request $request) use ($year) {
        $domains = $request['includeDomains'] ?? [];

        if (in_array('libroazul.com', $domains, true)) {
            return Http::response([
                'data' => [
                    'web' => [
                        [
                            'url' => 'https://www.libroazul.com/toyota/corolla',
                            'json' => [
                                'valuaciones' => [
                                    [
                                        'modelo' => 'Corolla',
                                        'anio' => $year,
                                        'moneda' => 'MXN',
                                        'tipo_registro' => 'libro_azul',
                                        'precio_compra' => 270000,
                                        'precio_venta' => 310000,
                                        'precio_sugerido' => 300000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response(['data' => ['web' => []]]);
    });

    $lowKm = $this->getJson('/api/car-price-suggestions/market?model_id='.$model->getKey().'&year='.$year.'&km=10000')
        ->assertOk()
        ->json();

    $highKm = $this->getJson('/api/car-price-suggestions/market?model_id='.$model->getKey().'&year='.$year.'&km=130000')
        ->assertOk()
        ->json();

    Http::assertSentCount(2);

    expect($lowKm['cached'])->toBeFalse()
        ->and($lowKm['km_adjusted'])->toBeTrue()
        ->and($lowKm['suggested'])->toBeGreaterThan(300000)
        ->and($highKm['cached'])->toBeTrue()
        ->and($highKm['km_adjusted'])->toBeTrue()
        ->and($highKm['suggested'])->toBeLessThan(300000);

    $refreshed = $this->getJson('/api/car-price-suggestions/market?model_id='.$model->getKey().'&year='.$year.'&km=60000&refresh=1')
        ->assertOk()
        ->json();

    Http::assertSentCount(4);

    expect($refreshed['cached'])->toBeFalse()
        ->and($refreshed['suggested'])->toBe(300000);
});

it('prefers exact year comparables when enough samples exist before falling back to year range', function () {
    $model = priceModel();
    priceCar($model, 300000, 2022, 'en_venta', true, 'ex-1');
    priceCar($model, 310000, 2022, 'en_venta', true, 'ex-2');
    priceCar($model, 320000, 2022, 'en_venta', true, 'ex-3');
    priceCar($model, 180000, 2020, 'en_venta', true, 'old-1');
    priceCar($model, 190000, 2020, 'en_venta', true, 'old-2');

    $service = app(CarPriceSuggestionService::class);
    $exact = $service->comparables($model, 2022);
    $range = $service->comparables($model, 2021);

    expect($exact['samples'])->toBe(3)
        ->and($exact['year_scope'])->toBe('exact')
        ->and($exact['min'])->toBeGreaterThanOrEqual(300000)
        ->and($range['samples'])->toBe(5)
        ->and($range['year_scope'])->toBe('range');
});
