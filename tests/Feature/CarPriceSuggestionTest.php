<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Services\Pricing\CarPriceSuggestionService;
use App\Services\Pricing\MarketPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                    ['url' => 'https://example.com/a', 'json' => ['precio_minimo' => '1.200.000', 'precio_sugerido' => '1.300.000', 'precio_maximo' => '1.400.000']],
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
        ->and($second['cached'])->toBeTrue();

    Http::assertSentCount(1);
});

it('returns a friendly market error when firecrawl is not configured', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', null);

    $result = app(MarketPriceService::class)->suggest(priceModel(), 2022, 'LE', 45000);

    expect($result['suggested'])->toBeNull()
        ->and($result['error'])->toContain('FIRECRAWL_API_KEY');
});
