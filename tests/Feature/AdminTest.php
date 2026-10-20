<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarReport;
use App\Models\CarStatus;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminUser(string $role = 'superadmin'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function adminCar(): Car
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
        'slug' => 'admin-test-car',
        'year' => 2022,
        'km' => 45000,
        'price' => 300000,
        'city' => 'Monterrey, NL',
        'description' => 'Auto de prueba para admin.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('renders the admin dashboard for superadmin', function () {
    $this->seed();
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
});

it('blocks regular users from admin', function () {
    $this->seed();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows viewers to read but not modify cars', function () {
    $this->seed();
    $viewer = adminUser('viewer');
    $car = adminCar();

    $this->actingAs($viewer)->get('/admin/autos')->assertOk();

    $this->actingAs($viewer)
        ->patch("/admin/autos/{$car->getKey()}", ['featured' => true])
        ->assertForbidden();
});

it('allows managers to moderate cars', function () {
    $this->seed();
    $manager = adminUser('manager');
    $car = adminCar();

    $this->actingAs($manager)
        ->patch("/admin/autos/{$car->getKey()}", ['featured' => true, 'is_published' => false])
        ->assertRedirect();

    expect($car->fresh()->featured)->toBeTrue();
    expect($car->fresh()->is_published)->toBeFalse();
});

it('allows superadmin to update user roles', function () {
    $this->seed();
    $admin = adminUser();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)
        ->patch("/admin/usuarios/{$target->getKey()}", [
            'roles' => ['manager'],
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($target->fresh()->hasRole('manager'))->toBeTrue();
    expect($target->fresh()->is_active)->toBeFalse();
});

it('blocks managers from updating user roles', function () {
    $this->seed();
    $manager = adminUser('manager');
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($manager)
        ->patch("/admin/usuarios/{$target->getKey()}", [
            'roles' => ['manager'],
            'is_active' => true,
        ])
        ->assertForbidden();
});

it('creates catalog entries from admin', function () {
    $this->seed();
    $admin = adminUser();

    $this->actingAs($admin)
        ->post('/admin/catalogos/brands', ['name' => 'Nueva Marca'])
        ->assertRedirect();

    expect(CarBrand::query()->where('slug', 'nueva-marca')->exists())->toBeTrue();
});

it('creates plans from admin', function () {
    $this->seed();
    $admin = adminUser();

    $this->actingAs($admin)
        ->post('/admin/planes', [
            'key_name' => 'plan_test',
            'name' => 'Plan Test',
            'price' => 100,
            'duration' => '30 días',
            'duration_weeks' => 4,
            'max_cars' => 2,
            'highlight' => false,
            'features' => ['Feature 1'],
            'sort_order' => 10,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Plan::query()->where('key_name', 'plan_test')->exists())->toBeTrue();
});

it('updates payment toggles from admin settings', function () {
    $this->seed();
    $admin = adminUser();

    $this->actingAs($admin)
        ->patch('/admin/configuracion', [
            'key' => 'payments',
            'value' => [
                'stripe_enabled' => true,
                'paypal_enabled' => false,
                'spei_enabled' => true,
                'tokens_enabled' => false,
                'membership_enabled' => false,
            ],
        ])
        ->assertRedirect();

    $payments = SiteSetting::query()->where('key', 'payments')->value('value');

    expect($payments['stripe_enabled'])->toBeTrue();
    expect($payments['spei_enabled'])->toBeTrue();
});

it('updates car reports from admin', function () {
    $this->seed();
    $admin = adminUser();
    $car = adminCar();

    $report = CarReport::query()->create([
        'car_id' => $car->getKey(),
        'reason' => 'Información falsa',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/reportes/{$report->getKey()}", ['status' => 'resolved'])
        ->assertRedirect();

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->resolved_at)->not->toBeNull();
});
