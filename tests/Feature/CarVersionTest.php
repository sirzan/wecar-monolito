<?php

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarVersion;
use App\Models\User;
use App\Services\CarCatalog\CarVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function versionModel(): CarModel
{
    $brand = CarBrand::query()->firstOrCreate(
        ['slug' => 'toyota'],
        ['name' => 'Toyota'],
    );

    return CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
}

it('returns stored versions without calling firecrawl', function () {
    Cache::flush();
    Http::fake();

    $model = versionModel();

    CarVersion::query()->create([
        'model_id' => $model->getKey(),
        'name' => 'LE',
        'year' => '2023',
    ]);

    $versions = app(CarVersionService::class)->versionsFor($model, 2023);

    expect($versions)->toHaveCount(1)
        ->and($versions[0]['name'])->toBe('LE');

    Http::assertNothingSent();
});

it('fetches versions from firecrawl and stores them', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => [
                'web' => [[
                    'json' => [
                        'versiones' => ['XLE', 'LE', 'XLE', 'SE'],
                    ],
                ]],
            ],
        ]),
    ]);

    $model = versionModel();
    $versions = app(CarVersionService::class)->versionsFor($model, 2024);

    expect($versions)->toHaveCount(3)
        ->and(array_column($versions, 'name'))->toBe(['LE', 'SE', 'XLE'])
        ->and(CarVersion::query()->where('model_id', $model->getKey())->count())->toBe(3);
});

it('uses local versions before firecrawl even after the cache is cleared', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-key');

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => ['web' => [['json' => ['versiones' => ['XLE']]]]],
        ]),
    ]);

    $model = versionModel();
    $service = app(CarVersionService::class);

    $service->versionsFor($model, 2024);
    Cache::flush();
    $service->versionsFor($model, 2024);

    Http::assertSentCount(1);
});

it('exposes versions through the catalog endpoint', function () {
    $model = versionModel();

    CarVersion::query()->create([
        'model_id' => $model->getKey(),
        'name' => 'XLE',
        'year' => '2023',
    ]);

    $this->getJson('/api/car-versions?model_id='.$model->getKey().'&year=2023')
        ->assertOk()
        ->assertJsonPath('versions.0.name', 'XLE');
});

it('requires a valid version to publish a car', function () {
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );

    $this->actingAs($user)->post('/vende', [
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'year' => 2023,
        'km' => 12000,
        'price' => 350000,
        'description' => 'Auto en perfectas condiciones con servicio al día.',
        'city' => 'Monterrey, NL',
        'images' => [],
    ])->assertSessionHasErrors(['version_id', 'images']);
});
