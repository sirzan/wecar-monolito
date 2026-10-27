<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\Financiera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeCar(): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);

    return Car::query()->create([
        'owner_id' => null,
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $status->getKey(),
        'slug' => 'toyota-corolla-2022-abc123',
        'year' => 2022,
        'km' => 45000,
        'price' => 320000,
        'city' => 'Monterrey, NL',
        'description' => 'Toyota Corolla 2022 en excelente estado.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('renders the catalog with paginated options', function () {
    $this->seed();
    makeCar();

    $this->get('/comprar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Comprar')
            ->has('cars.data', 1)
            ->has('options.brands')
        );
});

it('filters the catalog by brand', function () {
    $this->seed();
    makeCar();

    $this->get('/comprar?brand=toyota')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Comprar')
            ->has('cars.data', 1)
            ->where('filters.brand', 'toyota')
        );
});

it('renders a car detail page with slug and seo', function () {
    $this->seed();
    $car = makeCar();

    $this->get('/auto/'.$car->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/AutoDetalle')
            ->where('car.slug', $car->slug)
            ->has('seo.title')
            ->where('financing.min_rate', fn ($rate) => (float) $rate > 0)
            ->where('financing.max_term', fn ($term) => (int) $term >= 12)
        );
});

it('redirects an old uuid car url to the slug url', function () {
    $this->seed();
    $car = makeCar();

    $this->get('/auto/'.$car->getKey())
        ->assertRedirect('/auto/'.$car->slug);
});

it('requires authentication to publish a car', function () {
    $this->seed();

    $this->post('/vende', [])->assertRedirect('/login');
});

it('publishes a car with images for a verified user', function () {
    $this->seed();
    Storage::fake('public');

    $user = User::factory()->create(['email_verified_at' => now()]);
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

    $response = $this->actingAs($user)->post('/vende', [
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
        'images' => [UploadedFile::fake()->image('carro.jpg')],
    ]);

    $car = Car::query()->where('owner_id', $user->getKey())->first();

    expect($car)->not->toBeNull();
    expect($car->gallery)->toHaveCount(1);
    expect($car->version_id)->toBe($version->getKey());

    $response->assertRedirect('/auto/'.$car->slug);
});

it('seeds all 39 financieras correctly from FinancieraSeeder', function () {
    $this->seed();

    expect(Financiera::count())->toBe(39);

    $bbva = Financiera::where('slug', 'bbva')->first();
    expect($bbva)->not->toBeNull()
        ->and($bbva->nombre)->toBe('BBVA Auto')
        ->and($bbva->tipo)->toBe('banco')
        ->and($bbva->destacado)->toBeTrue();

    $kavak = Financiera::where('slug', 'kavak')->first();
    expect($kavak)->not->toBeNull()
        ->and($kavak->tipo)->toBe('fintech')
        ->and((int) $kavak->score_min)->toBe(540);
});
