<?php

namespace Tests\Feature;

use App\Models\CarBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarBrandAndModelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_car_brands_and_models_are_seeded_by_migration(): void
    {
        // Verificar marcas clave
        $this->assertDatabaseHas('car_brands', ['name' => 'Nissan', 'slug' => 'nissan']);
        $this->assertDatabaseHas('car_brands', ['name' => 'Chevrolet', 'slug' => 'chevrolet']);
        $this->assertDatabaseHas('car_brands', ['name' => 'Volkswagen', 'slug' => 'volkswagen']);
        $this->assertDatabaseHas('car_brands', ['name' => 'Toyota', 'slug' => 'toyota']);
        $this->assertDatabaseHas('car_brands', ['name' => 'Alfa Romeo', 'slug' => 'alfa-romeo']);

        // Verificar modelos de Nissan
        $nissan = CarBrand::query()->where('slug', 'nissan')->firstOrFail();
        $this->assertGreaterThanOrEqual(11, $nissan->models()->count());
        $this->assertDatabaseHas('car_models', ['brand_id' => $nissan->getKey(), 'name' => 'Versa', 'slug' => 'versa']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $nissan->getKey(), 'name' => 'Sentra', 'slug' => 'sentra']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $nissan->getKey(), 'name' => 'X-Trail', 'slug' => 'x-trail']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $nissan->getKey(), 'name' => 'NP300', 'slug' => 'np300']);

        // Verificar modelos de Chevrolet
        $chevrolet = CarBrand::query()->where('slug', 'chevrolet')->firstOrFail();
        $this->assertGreaterThanOrEqual(14, $chevrolet->models()->count());
        $this->assertDatabaseHas('car_models', ['brand_id' => $chevrolet->getKey(), 'name' => 'Aveo', 'slug' => 'aveo']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $chevrolet->getKey(), 'name' => 'Silverado', 'slug' => 'silverado']);

        // Verificar modelos de Volkswagen
        $vw = CarBrand::query()->where('slug', 'volkswagen')->firstOrFail();
        $this->assertGreaterThanOrEqual(12, $vw->models()->count());
        $this->assertDatabaseHas('car_models', ['brand_id' => $vw->getKey(), 'name' => 'Jetta', 'slug' => 'jetta']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $vw->getKey(), 'name' => 'Vento', 'slug' => 'vento']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $vw->getKey(), 'name' => 'Taos', 'slug' => 'taos']);

        // Verificar modelos de Toyota
        $toyota = CarBrand::query()->where('slug', 'toyota')->firstOrFail();
        $this->assertGreaterThanOrEqual(13, $toyota->models()->count());
        $this->assertDatabaseHas('car_models', ['brand_id' => $toyota->getKey(), 'name' => 'Hilux', 'slug' => 'hilux']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $toyota->getKey(), 'name' => 'Tacoma', 'slug' => 'tacoma']);
        $this->assertDatabaseHas('car_models', ['brand_id' => $toyota->getKey(), 'name' => 'RAV4', 'slug' => 'rav4']);
    }
}
