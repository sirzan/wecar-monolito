<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\ExternalSearchCache;
use App\Models\ExternalSearchUserUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminPublishPayload(CarBrand $brand, CarModel $model, CarVersion $version, Plan $plan): array
{
    return [
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'version_id' => $version->getKey(),
        'year' => 2023,
        'km' => 12000,
        'price' => 350000,
        'description' => 'Auto en perfectas condiciones con servicio al día.',
        'transmission' => 'Automática',
        'fuel' => 'Gasolina',
        'body' => 'Sedán',
        'color' => 'Blanco',
        'city' => 'Monterrey, NL',
        'plan_key' => $plan->key_name,
        'images' => [UploadedFile::fake()->image('carro.jpg')],
    ];
}

function adminExternalCar(string $url, string $title = 'Toyota Corolla 2020'): array
{
    return [
        'id' => app(ExternalCarNormalizer::class)->externalId($url),
        'title' => $title,
        'price' => 300000,
        'year' => 2020,
        'km' => 50000,
        'location' => 'Monterrey',
        'imageUrl' => 'https://http2.mlstatic.com/car.jpg',
        'gallery' => [],
        'sourceUrl' => $url,
        'platformId' => 'mercadolibre',
        'platformName' => 'Mercado Libre Autos',
        'platformColor' => '#FFE600',
        'platformInitials' => 'ML',
    ];
}

it('lets superadmins and managers publish paid plans without checkout', function () {
    $this->seed();
    Storage::fake('public');

    $plan = Plan::query()->where('type', 'publication')->where('price', '>', 0)->firstOrFail();
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $version = CarVersion::query()->create([
        'model_id' => $model->getKey(),
        'name' => 'LE',
        'year' => '2023',
    ]);
    CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);

    foreach (['superadmin', 'manager'] as $role) {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole($role);

        $response = $this->actingAs($admin)->post('/vende', adminPublishPayload($brand, $model, $version, $plan));

        $car = Car::query()->where('owner_id', $admin->getKey())->firstOrFail();

        $response->assertRedirect('/auto/'.$car->slug);
        expect($car->status?->name)->toBe('en_venta')
            ->and($car->plan)->toBe($plan->key_name)
            ->and(Subscription::query()->where('user_id', $admin->getKey())->exists())->toBeFalse();
    }
});

it('keeps the normal checkout flow for viewers', function () {
    $this->seed();
    Storage::fake('public');

    $plan = Plan::query()->where('type', 'publication')->where('price', '>', 0)->firstOrFail();
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $version = CarVersion::query()->create([
        'model_id' => $model->getKey(),
        'name' => 'LE',
        'year' => '2023',
    ]);
    CarStatus::query()->firstOrCreate(['name' => 'pendiente'], ['label' => 'Pendiente']);

    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->post('/vende', adminPublishPayload($brand, $model, $version, $plan))
        ->assertRedirect();

    expect(Subscription::query()->where('user_id', $viewer->getKey())->where('status', 'pending')->exists())->toBeTrue();
});

it('gives admins unlimited external searches and direct source urls', function () {
    $this->seed();
    config()->set('services.firecrawl.key', 'test-key');

    $admin = User::factory()->create();
    $admin->assignRole('superadmin');

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => [[
                'url' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
                'json' => [
                    'autos' => [[
                        'title' => 'Toyota Corolla 2020',
                        'price' => 300000,
                        'year' => 2020,
                        'km' => 50000,
                        'location' => 'CDMX',
                        'imageUrl' => 'https://http2.mlstatic.com/corolla.jpg',
                        'sourceUrl' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
                    ]],
                ],
            ]],
        ]),
        '*' => Http::response('', 200),
    ]);

    for ($i = 0; $i < 4; $i++) {
        $this->actingAs($admin)
            ->postJson('/api/external-search', ['q' => 'toyota corolla'])
            ->assertOk()
            ->assertJsonPath('limitReached', false);
    }

    $this->actingAs($admin)
        ->getJson('/api/external-search/status')
        ->assertOk()
        ->assertJsonPath('unlimited', true)
        ->assertJsonPath('is_admin', true);

    expect(ExternalSearchUserUsage::query()->where('user_id', $admin->getKey())->exists())->toBeFalse();

    $car = [
        'id' => app(ExternalCarNormalizer::class)->externalId('https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020'),
        'title' => 'Toyota Corolla 2020',
        'price' => 300000,
        'year' => 2020,
        'km' => 50000,
        'location' => 'CDMX',
        'imageUrl' => 'https://http2.mlstatic.com/corolla.jpg',
        'gallery' => [],
        'sourceUrl' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
        'platformId' => 'mercadolibre',
        'platformName' => 'Mercado Libre Autos',
        'platformColor' => '#FFE600',
        'platformInitials' => 'ML',
    ];

    ExternalSearchCache::query()->create([
        'query_key' => 'admin-search',
        'query_text' => 'toyota',
        'platforms' => [],
        'results' => [$car],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    $this->actingAs($admin)
        ->getJson('/api/external-search/cached')
        ->assertOk()
        ->assertJsonPath('results.0.sourceUrl', $car['sourceUrl']);

    $this->actingAs($admin)
        ->postJson('/api/external-search/resolve', ['id' => $car['id']])
        ->assertOk()
        ->assertJsonPath('allowed', true)
        ->assertJsonPath('sourceUrl', $car['sourceUrl']);
});

it('allows managers to delete external cars across groups and delete a full group', function () {
    $this->seed();

    $manager = User::factory()->create(['email_verified_at' => now()]);
    $manager->assignRole('manager');

    $shared = adminExternalCar('https://autos.mercadolibre.com.mx/MLM-shared');
    $other = adminExternalCar('https://autos.mercadolibre.com.mx/MLM-other', 'Nissan Sentra 2021');
    $groupOne = ExternalSearchCache::query()->create([
        'query_key' => 'admin-group-one',
        'query_text' => 'toyota',
        'platforms' => [],
        'results' => [$shared, $other],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);
    $groupTwo = ExternalSearchCache::query()->create([
        'query_key' => 'admin-group-two',
        'query_text' => 'corolla',
        'platforms' => [],
        'results' => [$shared],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    $this->actingAs($manager)
        ->get('/admin/autos-externos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ExternalAutos')
            ->has('groups.data', 2)
        );

    $this->actingAs($manager)
        ->post('/admin/autos-externos/seleccion', ['ids' => [$shared['id']]])
        ->assertRedirect();

    expect($groupOne->fresh()->results)->toHaveCount(1)
        ->and($groupTwo->fresh())->toBeNull();

    $this->actingAs($manager)
        ->delete("/admin/autos-externos/grupos/{$groupOne->getKey()}")
        ->assertRedirect();

    expect(ExternalSearchCache::query()->whereKey($groupOne->getKey())->exists())->toBeFalse();
});

it('lets viewers inspect external cars but not delete them', function () {
    $this->seed();

    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('viewer');
    $car = adminExternalCar('https://autos.mercadolibre.com.mx/MLM-viewer');
    $group = ExternalSearchCache::query()->create([
        'query_key' => 'viewer-group',
        'query_text' => 'viewer',
        'platforms' => [],
        'results' => [$car],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    $this->actingAs($viewer)->get('/admin/autos-externos')->assertOk();

    $this->actingAs($viewer)
        ->delete('/admin/autos-externos/'.$car['id'])
        ->assertForbidden();

    expect(ExternalSearchCache::query()->whereKey($group->getKey())->exists())->toBeTrue();
});
