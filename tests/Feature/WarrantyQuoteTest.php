<?php

use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WarrantyQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function warrantyUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function warrantyPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'phone' => '8112345678',
        'car_brand' => 'Toyota',
        'car_model' => 'Corolla',
        'car_version' => 'LE CVT',
        'car_year' => 2023,
        'car_km' => 42000,
        'car_price' => 250000,
    ], $overrides);
}

it('requires authentication to create a warranty quote', function () {
    $this->postJson('/garantia/cotizaciones', warrantyPayload())->assertUnauthorized();
});

it('creates a consecutive warranty quote with server side amounts', function () {
    $user = warrantyUser();

    $first = $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', warrantyPayload())
        ->assertCreated()
        ->assertJsonPath('folio', 'WG-000001')
        ->assertJsonPath('consecutive', 1)
        ->assertJsonPath('service_fee_rate', 0.025)
        ->assertJsonPath('service_fee', 6250)
        ->assertJsonPath('total', 256250)
        ->assertJsonPath('currency', 'MXN');

    $pdfUrl = $first->json('pdf_url');

    expect($pdfUrl)->toBeString()->toContain('/garantia/cotizaciones/');

    $quote = WarrantyQuote::query()->firstOrFail();

    expect($quote->user_id)->toBe($user->getKey())
        ->and($quote->folio)->toBe('WG-000001')
        ->and($quote->status)->toBe('issued')
        ->and($quote->includes)->not->toBeEmpty()
        ->and($quote->valid_until?->toDateString())->toBe(now()->addDays(15)->toDateString());

    $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', warrantyPayload(['email' => 'otra@example.com']))
        ->assertCreated()
        ->assertJsonPath('folio', 'WG-000002')
        ->assertJsonPath('consecutive', 2);
});

it('validates quote data and price range', function () {
    $user = warrantyUser();

    $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'email', 'car_brand', 'car_model', 'car_year', 'car_price']);

    $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', warrantyPayload(['car_price' => 1000]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_price');

    $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', warrantyPayload(['car_price' => 5000000]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_price');

    $this->actingAs($user)
        ->postJson('/garantia/cotizaciones', warrantyPayload(['car_year' => 1980]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_year');
});

it('uses the service fee rate configured by the admin', function () {
    SiteSetting::put('warranty_config', ['service_fee_rate' => 3]);

    $this->actingAs(warrantyUser())
        ->postJson('/garantia/cotizaciones', warrantyPayload(['car_price' => 100000]))
        ->assertCreated()
        ->assertJsonPath('service_fee_rate', 0.03)
        ->assertJsonPath('service_fee', 3000)
        ->assertJsonPath('total', 103000);
});

it('streams the quote pdf only to its owner or admins', function () {
    $owner = warrantyUser();
    $other = warrantyUser();

    $this->actingAs($owner)
        ->postJson('/garantia/cotizaciones', warrantyPayload())
        ->assertCreated();

    $quote = WarrantyQuote::query()->firstOrFail();
    $url = route('garantia.cotizaciones.pdf', $quote);

    $response = $this->actingAs($owner)->get($url)->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();

    $this->actingAs($other)->get($url)->assertForbidden();

    Role::findOrCreate('manager', 'web');
    $admin = warrantyUser();
    $admin->assignRole('manager');

    $this->actingAs($admin)->get($url)->assertOk();
});

it('lists warranty quotes in the admin panel', function () {
    $this->seed();

    $user = warrantyUser();
    $this->actingAs($user)->postJson('/garantia/cotizaciones', warrantyPayload())->assertCreated();

    $admin = warrantyUser();
    $admin->assignRole('superadmin');

    $this->actingAs($admin)
        ->get('/admin/cotizaciones-garantia')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/WarrantyQuotes')
            ->has('quotes.data', 1)
            ->where('quotes.data.0.folio', 'WG-000001')
            ->where('quotes.data.0.email', 'ana@example.com')
        );

    $viewer = warrantyUser();
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)->get('/admin/cotizaciones-garantia')->assertOk();
});

it('renders the warranty page with quote configuration for guests', function () {
    $this->seed();

    $this->get('/garantia')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Garantia')
            ->where('quote.service_fee_rate', 0.025)
            ->where('quote.valid_days', 15)
            ->where('quote.price_min', 50000)
            ->where('quote.price_max', 1500000)
            ->where('profile', null)
        );
});
