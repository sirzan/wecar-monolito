<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function loginUser(string $role, string $email): User
{
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('Password123'),
    ]);

    $user->assignRole($role);

    return $user;
}

it('redirects admins and managers to the admin panel after login', function (string $role) {
    $this->seed();
    $user = loginUser($role, $role.'@example.com');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Password123',
    ])->assertRedirect('/admin');
})->with(['superadmin', 'manager']);

it('keeps normal users going to their panel', function () {
    $this->seed();
    $user = User::factory()->create([
        'email' => 'seller@example.com',
        'password' => Hash::make('Password123'),
    ]);
    $user->assignRole('seller');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Password123',
    ])->assertRedirect('/panel');
});

it('respects an intended admin url for admins', function () {
    $this->seed();
    $user = loginUser('superadmin', 'admin-intended@example.com');

    $this->withSession(['url.intended' => url('/admin/autos')])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect('/admin/autos');
});

it('renders the publication wizard inside the admin layout', function () {
    $this->seed();
    $admin = loginUser('superadmin', 'admin-create@example.com');

    $this->actingAs($admin)
        ->get('/admin/autos/crear')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Vende')
            ->where('adminMode', true)
            ->has('brands')
            ->has('plans')
        );
});

it('blocks viewers from the admin publication page', function () {
    $this->seed();
    $viewer = loginUser('viewer', 'viewer-create@example.com');

    $this->actingAs($viewer)->get('/admin/autos/crear')->assertForbidden();
});

it('publishes an admin car and returns to the admin list', function () {
    $this->seed();
    Storage::fake('public');

    $admin = loginUser('superadmin', 'admin-publish@example.com');
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

    $response = $this->actingAs($admin)->post('/admin/autos', [
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
    ]);

    $car = Car::query()->where('owner_id', $admin->getKey())->firstOrFail();

    $response->assertRedirect('/admin/autos');
    expect($car->status?->name)->toBe('en_venta')
        ->and(Subscription::query()->where('user_id', $admin->getKey())->exists())->toBeFalse();
});
