<?php

use App\Mail\ContactMessage;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createCarForComparison(string $slug, int $price): Car
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
        'slug' => $slug,
        'year' => 2022,
        'km' => 45000,
        'price' => $price,
        'city' => 'Monterrey, NL',
        'description' => 'Auto de prueba en excelente estado.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('shows the selected cars on the compare page', function () {
    $this->seed();
    $first = createCarForComparison('toyota-corolla-2022-one', 300000);
    $second = createCarForComparison('toyota-corolla-2023-two', 350000);

    $this->get('/comparar?ids='.$first->getKey().','.$second->getKey())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Comparar')
            ->has('cars', 2)
            ->where('cars.0.id', $first->getKey())
            ->where('cars.1.id', $second->getKey())
        );
});

it('sends the contact form email', function () {
    $this->seed();
    Mail::fake();

    $this->post('/contacto', [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'phone' => '8112345678',
        'message' => 'Quiero información sobre un auto.',
    ])->assertRedirect();

    Mail::assertQueued(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->data['email'] === 'ana@example.com';
    });
});

it('validates the contact form', function () {
    $this->post('/contacto', [])->assertSessionHasErrors(['name', 'email', 'message']);
});
