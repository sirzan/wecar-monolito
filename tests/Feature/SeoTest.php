<?php

use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seoCar(): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);

    return Car::query()->create([
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $status->getKey(),
        'slug' => 'toyota-corolla-2022-seo',
        'year' => 2022,
        'km' => 45000,
        'price' => 300000,
        'city' => 'Monterrey, NL',
        'body' => 'Sedán',
        'description' => 'Auto para pruebas SEO.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('marks filtered catalog pages as noindex', function () {
    $this->seed();
    seoCar();

    $this->get('/comprar')
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', false));

    $this->get('/comprar?brand=toyota')
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', true));
});

it('renders brand landing pages with canonical seo', function () {
    $this->seed();
    seoCar();

    $this->get('/comprar/marca/toyota')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Comprar')
            ->where('filters.brand', 'toyota')
            ->where('seo.noindex', false)
        );
});

it('includes cars, agencies and landing pages in the sitemap', function () {
    $this->seed();
    $car = seoCar();

    $owner = User::factory()->create();
    $agency = Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-seo',
        'name' => 'Agencia SEO',
        'is_active' => true,
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $content = $response->getContent();

    expect($content)->toContain('/auto/'.$car->slug);
    expect($content)->toContain('/agencia/'.$agency->slug);
    expect($content)->toContain('/comprar/marca/toyota');
});

it('has a robots file pointing to the sitemap', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /admin');
    expect($robots)->toContain('Sitemap: https://wecar.mx/sitemap.xml');
});
