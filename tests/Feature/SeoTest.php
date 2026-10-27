<?php

use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\ExternalSearchCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seoCar(string $slug = 'toyota-corolla-2022-seo', string $status = 'en_venta', array $overrides = []): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $statusModel = CarStatus::query()->firstOrCreate(['name' => $status], ['label' => ucfirst($status)]);

    return Car::query()->create(array_merge([
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $statusModel->getKey(),
        'slug' => $slug,
        'year' => 2022,
        'km' => 45000,
        'price' => 300000,
        'city' => 'Monterrey, NL',
        'body' => 'Sedán',
        'description' => 'Auto para pruebas SEO.',
        'image' => '/storage/cars/seo.jpg',
        'is_published' => true,
        'published_at' => now(),
    ], $overrides));
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

it('generates a sitemap index with internal published cars only', function () {
    $this->seed();
    Storage::fake('sitemaps');
    config()->set('seo.base_url', 'https://wecar.mx');

    $car = seoCar();
    $sold = seoCar('toyota-corolla-vendido', 'vendido');
    $paused = seoCar('toyota-corolla-pausado', 'pausado');

    $owner = User::factory()->create();
    $agency = Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-seo',
        'name' => 'Agencia SEO',
        'is_active' => true,
    ]);

    ExternalSearchCache::query()->create([
        'query_key' => 'external-sitemap-test',
        'query_text' => 'toyota corolla',
        'platforms' => [],
        'results' => [[
            'id' => 'ext-1',
            'title' => 'Toyota Corolla externa',
            'sourceUrl' => 'https://autos.mercadolibre.com.mx/MLM-123',
            'platformId' => 'mercadolibre',
        ]],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    Storage::disk('sitemaps')->put('cars-99.xml', '<urlset></urlset>');

    $this->artisan('seo:generate-sitemap')->assertSuccessful();

    $index = Storage::disk('sitemaps')->get('sitemap.xml');
    $pages = Storage::disk('sitemaps')->get('pages.xml');
    $cars = Storage::disk('sitemaps')->get('cars-1.xml');

    expect($index)->toContain('https://wecar.mx/sitemaps/pages.xml')
        ->and($index)->toContain('https://wecar.mx/sitemaps/cars-1.xml')
        ->and($cars)->toContain('/auto/'.$car->slug)
        ->and($cars)->not->toContain('/auto/'.$sold->slug)
        ->and($cars)->not->toContain('/auto/'.$paused->slug)
        ->and($cars)->not->toContain('/auto/externo/')
        ->and($cars)->not->toContain('mercadolibre')
        ->and($pages)->toContain('/comprar/marca/toyota')
        ->and($pages)->toContain('/agencia/'.$agency->slug)
        ->and($pages)->toContain('https://wecar.mx/comprar/ciudad/Monterrey,%20NL')
        ->and(Storage::disk('sitemaps')->exists('cars-99.xml'))->toBeFalse();

    expect(simplexml_load_string($index))->not->toBeFalse()
        ->and(simplexml_load_string($cars))->not->toBeFalse();
});

it('splits cars into sitemap chunks and serves children', function () {
    $this->seed();
    Storage::fake('sitemaps');
    config()->set('seo.base_url', 'https://wecar.mx');
    config()->set('seo.cars_per_file', 1);

    $first = seoCar('toyota-corolla-seo-1');
    $second = seoCar('toyota-corolla-seo-2');

    $this->artisan('seo:generate-sitemap')->assertSuccessful();

    expect(Storage::disk('sitemaps')->exists('cars-1.xml'))->toBeTrue()
        ->and(Storage::disk('sitemaps')->exists('cars-2.xml'))->toBeTrue();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/sitemaps/cars-1.xml', false);

    $this->get('/sitemaps/cars-1.xml')
        ->assertOk()
        ->assertSee($first->slug);

    $this->get('/sitemaps/cars-2.xml')
        ->assertOk()
        ->assertSee($second->slug);

    $this->get('/sitemaps/../sitemap.xml')->assertNotFound();
});

it('builds car metadata and structured data from seo fields', function () {
    $this->seed();
    config()->set('seo.base_url', 'https://wecar.mx');

    $car = seoCar(overrides: [
        'seo_title' => 'Toyota Corolla 2022 certificado | Wecar',
        'seo_description' => 'Toyota Corolla 2022 con 45,000 km en Monterrey. Precio $300,000 MXN.',
    ]);

    $this->get('/auto/'.$car->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/AutoDetalle')
            ->where('seo.title', 'Toyota Corolla 2022 certificado | Wecar')
            ->where('seo.description', 'Toyota Corolla 2022 con 45,000 km en Monterrey. Precio $300,000 MXN.')
            ->where('seo.noindex', false)
            ->where('seo.robots', 'index,follow,max-image-preview:large')
            ->where('seo.availability', 'https://schema.org/InStock')
            ->where('seo.image', 'https://wecar.mx/storage/cars/seo.jpg')
        );
});

it('marks sold cars as noindex', function () {
    $this->seed();

    $car = seoCar('toyota-corolla-sold-seo', 'vendido');

    $this->get('/auto/'.$car->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.noindex', true)
            ->where('seo.robots', 'noindex,follow')
            ->where('seo.availability', 'https://schema.org/SoldOut')
        );
});

it('has a robots file pointing to the sitemap and blocking external listings', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /admin')
        ->and($robots)->toContain('Disallow: /auto/externo/')
        ->and($robots)->toContain('Sitemap: https://wecar.mx/sitemap.xml');
});
