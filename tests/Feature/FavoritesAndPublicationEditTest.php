<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function editableCar(User $owner, array $attributes = []): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'mazda'], ['name' => 'Mazda']);
    $model = CarModel::query()->firstOrCreate(['brand_id' => $brand->getKey(), 'slug' => 'mazda-3'], ['name' => 'Mazda 3']);
    $version = CarVersion::query()->firstOrCreate(
        ['model_id' => $model->getKey(), 'name' => 'i Grand Touring', 'year' => '2022'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);

    return Car::query()->create([
        'owner_id' => $owner->getKey(),
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'version_id' => $version->getKey(),
        'status_id' => $status->getKey(),
        'slug' => 'mazda-3-'.$owner->getKey(),
        'year' => 2022,
        'km' => 30000,
        'price' => 350000,
        'description' => 'Auto de prueba con descripción suficiente para editar.',
        'city' => 'Monterrey, NL',
        'image' => '/storage/cars/portada.jpg',
        'gallery' => ['/storage/cars/portada.jpg', '/storage/cars/foto-2.jpg'],
        'is_published' => true,
        'published_at' => now(),
        ...$attributes,
    ]);
}

it('persists favorites and lists them in the panel', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $car = editableCar($user);

    $this->actingAs($user)
        ->postJson("/api/favorites/{$car->getKey()}")
        ->assertOk()
        ->assertJson(['favorited' => true]);

    $this->assertDatabaseHas('favorites', ['user_id' => $user->getKey(), 'car_id' => $car->getKey()]);

    $this->actingAs($user)
        ->get('/panel/favoritos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Panel/Favorites')
            ->has('items', 1)
            ->where('items.0.available', true)
        );

    $this->actingAs($user)
        ->postJson("/api/favorites/{$car->getKey()}")
        ->assertOk()
        ->assertJson(['favorited' => false]);

    $this->assertDatabaseMissing('favorites', ['user_id' => $user->getKey(), 'car_id' => $car->getKey()]);
});

it('syncs guest favorites after login without duplicates', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $car = editableCar($user);

    $this->actingAs($user)
        ->postJson('/api/favorites/sync', ['ids' => [$car->getKey(), $car->getKey()]])
        ->assertOk()
        ->assertJsonCount(1, 'ids');

    $this->assertDatabaseCount('favorites', 1);
});

it('allows the owner to edit a publication and keeps its images', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $car = editableCar($user);

    $this->actingAs($user)
        ->get("/panel/publicaciones/{$car->getKey()}/editar")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Vende')
            ->where('editMode', true)
            ->where('car.id', $car->getKey())
        );

    $this->actingAs($user)
        ->patch("/panel/publicaciones/{$car->getKey()}", [
            'brand_id' => $car->brand_id,
            'model_id' => $car->model_id,
            'version_id' => $car->version_id,
            'year' => 2022,
            'km' => 31500,
            'price' => 365000,
            'description' => 'Descripción actualizada con todos los datos del vehículo.',
            'city' => 'Monterrey, NL',
            'state' => 'Nuevo León',
            'transmission' => 'Automática',
            'fuel' => 'Gasolina',
            'body' => 'Sedán',
            'color' => 'Blanco',
            'interior_color' => 'Negro',
            'invoice_type' => 'Factura de seminuevo',
            'insurance_status' => 'Con seguro',
            'existing_images' => ['/storage/cars/portada.jpg', '/storage/cars/foto-2.jpg'],
            'primary_image' => '/storage/cars/foto-2.jpg',
        ])
        ->assertRedirect('/panel/publicaciones');

    $car->refresh();

    expect((float) $car->price)->toBe(365000.0)
        ->and($car->interior_color)->toBe('Negro')
        ->and($car->invoice_type)->toBe('Factura de seminuevo')
        ->and($car->insurance_status)->toBe('Con seguro')
        ->and($car->gallery)->toBe(['/storage/cars/portada.jpg', '/storage/cars/foto-2.jpg'])
        ->and($car->image)->toBe('/storage/cars/foto-2.jpg');
});

it('forbids editing a publication owned by another user', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $car = editableCar($owner);

    $this->actingAs($other)
        ->get("/panel/publicaciones/{$car->getKey()}/editar")
        ->assertForbidden();

    $this->actingAs($other)
        ->patch("/panel/publicaciones/{$car->getKey()}", [])
        ->assertForbidden();
});
