<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\ExternalListing;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the public home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Home'));
});

it('interleaves internal and external cars up to 16 most recent items on the home page', function () {
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(['brand_id' => $brand->getKey(), 'slug' => 'corolla'], ['name' => 'Corolla']);
    $status = CarStatus::query()->firstOrCreate(['name' => 'disponible'], ['label' => 'Disponible']);
    $owner = User::factory()->create();

    for ($i = 1; $i <= 3; $i++) {
        Car::query()->create([
            'owner_id' => $owner->getKey(),
            'brand_id' => $brand->getKey(),
            'model_id' => $model->getKey(),
            'status_id' => $status->getKey(),
            'slug' => 'toyota-corolla-home-'.$i,
            'title' => 'Toyota Corolla Local '.$i,
            'year' => 2020 + $i,
            'price' => 250000 + ($i * 10000),
            'km' => 30000,
            'city' => 'Monterrey',
            'is_published' => true,
            'published_at' => now()->subHours(10 - $i),
        ]);
    }

    for ($i = 1; $i <= 15; $i++) {
        $extId = Str::lower(Str::random(32));
        ExternalListing::query()->create([
            'external_id' => $extId,
            'source' => 'kavak',
            'source_url' => 'https://www.kavak.com/mx/venta/auto-home-'.$i,
            'slug' => 'auto-externo-home-'.$i.'-'.substr($extId, 0, 6),
            'title' => 'Mazda 3 Externo '.$i,
            'make' => 'Mazda',
            'model' => 'Mazda 3',
            'year' => 2022,
            'price' => 310000 + ($i * 1000),
            'mileage' => 25000,
            'location' => 'CDMX',
            'images' => ['https://images.example.com/car-'.$i.'.jpg'],
            'last_seen_at' => now()->subMinutes(20 - $i),
        ]);
    }

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->has('homeCars', 16)
            ->where('homeCars.0.kind', 'local')
            ->where('homeCars.0.car.slug', 'toyota-corolla-home-3')
            ->where('homeCars.1.kind', 'external')
            ->where('homeCars.1.car.title', 'Mazda 3 Externo 15')
            ->where('homeCars.2.kind', 'local')
            ->where('homeCars.3.kind', 'external')
            ->where('homeCars.4.kind', 'local')
            ->where('homeCars.5.kind', 'external')
        );
});

it('renders the public home page with the three publication plans', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->has('plans', 3)
            ->where('plans.0.key_name', 'basico')
            ->where('plans.1.key_name', 'destacado')
            ->where('plans.2.key_name', 'premium')
        );
});

it('renders the login page for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects guests from the panel and admin areas', function () {
    $this->get('/panel')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');
});

it('allows a superadmin to access the admin panel', function () {
    $this->seed();

    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
});

it('blocks a regular user from the admin panel', function () {
    $this->seed();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows a verified user to access the seller panel', function () {
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Panel/Dashboard'));
});
