<?php

use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function legalDetailsPublishPayload(CarBrand $brand, CarModel $model, CarVersion $version, Plan $plan, array $overrides = []): array
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
        ...$overrides,
    ];
}

function legalDetailsCatalog(): array
{
    test()->seed();

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

    return [$plan, $brand, $model, $version];
}

it('stores the optional legal and vehicle details when publishing', function () {
    Storage::fake('public');
    [$plan, $brand, $model, $version] = legalDetailsCatalog();

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('superadmin');

    $this->actingAs($admin)->post('/vende', legalDetailsPublishPayload($brand, $model, $version, $plan, [
        'invoice_type' => 'Factura original',
        'owners_count' => 2,
        'insurance_status' => 'Vigente',
        'vin' => '3VWFE21C04M000001',
        'serial_number' => 'SERIE-123456',
        'plates' => 'ABC-123-A',
    ]))->assertRedirect();

    $car = Car::query()->where('owner_id', $admin->getKey())->firstOrFail();

    expect($car->invoice_type)->toBe('Factura original')
        ->and($car->owners_count)->toBe(2)
        ->and($car->insurance_status)->toBe('Vigente')
        ->and($car->vin)->toBe('3VWFE21C04M000001')
        ->and($car->serial_number)->toBe('SERIE-123456')
        ->and($car->plates)->toBe('ABC-123-A');
});

it('only marks a car as verified when all legal details are present', function () {
    Storage::fake('public');
    [$plan, $brand, $model, $version] = legalDetailsCatalog();

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('superadmin');

    $this->actingAs($admin)->post('/vende', legalDetailsPublishPayload($brand, $model, $version, $plan, [
        'vin' => '3VWFE21C04M000001',
    ]))->assertRedirect();

    $partial = Car::query()->where('owner_id', $admin->getKey())->firstOrFail();

    expect((new CarResource($partial))->resolve()['verified'])->toBeFalse();

    $partial->update([
        'serial_number' => 'SERIE-123456',
        'plates' => 'ABC-123-A',
    ]);

    expect((new CarResource($partial))->resolve()['verified'])->toBeTrue();
});
