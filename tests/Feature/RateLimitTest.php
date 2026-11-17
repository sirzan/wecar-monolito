<?php

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('tracks guest rate limits by forwarded client ip behind proxies and renders spanish 429 json', function () {
    Cache::flush();
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    config()->set('services.firecrawl.key', 'test-key');

    $proxyA = ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10'];
    $proxyB = ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.11'];

    for ($i = 0; $i < 20; $i++) {
        $this->withServerVariables($proxyA)
            ->postJson('/api/external-search', ['q' => 'toyota 2025'])
            ->assertOk();
    }

    $blocked = $this->withServerVariables($proxyA)
        ->postJson('/api/external-search', ['q' => 'toyota 2025'])
        ->assertStatus(429);

    expect($blocked->json('message'))->toContain('Demasiadas solicitudes en poco tiempo')
        ->and($blocked->json('error'))->toContain('Demasiadas solicitudes en poco tiempo')
        ->and($blocked->json('retryAfter'))->toBeInt()->toBeGreaterThan(0);

    // Un segundo cliente detrás del mismo proxy no debe quedar bloqueado.
    $this->withServerVariables($proxyB)
        ->postJson('/api/external-search', ['q' => 'toyota 2025'])
        ->assertOk();
});

it('allows higher throughput for authenticated and admin users without triggering early 429', function () {
    Cache::flush();
    $this->seed();
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    config()->set('services.firecrawl.key', 'test-key');

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('superadmin');

    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $version = CarVersion::query()->firstOrCreate(
        ['model_id' => $model->getKey(), 'slug' => 'le-2024', 'year' => 2024],
        ['name' => 'LE CVT'],
    );

    $this->actingAs($admin);

    for ($i = 0; $i < 15; $i++) {
        $this->postJson('/api/external-search', ['q' => 'toyota '.$i])->assertOk();
    }

    for ($i = 0; $i < 25; $i++) {
        $this->getJson('/api/car-price-suggestions/market?'.http_build_query([
            'model_id' => $model->getKey(),
            'version_id' => $version->getKey(),
            'year' => 2024,
            'km' => 20000,
        ]))->assertOk();
    }
});
