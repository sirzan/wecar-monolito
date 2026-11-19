<?php

use App\Models\ExternalListing;
use App\Models\ExternalSearchCache;
use App\Models\ExternalSearchUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function externalListing(array $attributes = []): ExternalListing
{
    $externalId = $attributes['external_id'] ?? 'abc123def4567890abc123def4567890';

    return ExternalListing::query()->create([
        'external_id' => $externalId,
        'source' => 'mercadolibre',
        'source_url' => 'https://auto.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2022',
        'slug' => '2022-toyota-corolla-'.$externalId,
        'title' => 'Toyota Corolla 2022',
        'make' => 'toyota',
        'model' => 'corolla',
        'year' => 2022,
        'price' => 350000,
        'mileage' => 42000,
        'location' => 'Monterrey, NL',
        'images' => ['https://http2.mlstatic.com/D_NQ_NP_123.jpg'],
        'raw_data' => ['description' => 'Anuncio externo de prueba.'],
        'last_seen_at' => now(),
        ...$attributes,
    ]);
}

it('renders the external detail with the internal detail view', function () {
    $listing = externalListing();

    $this->get(route('external.detail', $listing->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/AutoDetalle')
            ->where('car.id', $listing->external_id)
            ->where('car.title', 'Toyota Corolla 2022')
            ->where('external.sourceName', 'mercadolibre')
            ->where('external.sourceUrl', $listing->source_url)
            ->where('external.stale', false)
            ->where('seo.noindex', true)
            ->where('seo.robots', 'noindex,follow')
        );
});

it('returns 404 for an unknown external listing', function () {
    $this->get('/auto-externo/2022-toyota-corolla-inexistente')->assertNotFound();
});

it('does not consume search or token usage when opening external detail', function () {
    $listing = externalListing();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $before = ExternalSearchUsage::query()->count();

    $this->actingAs($user)
        ->get(route('external.detail', $listing->slug))
        ->assertOk();

    expect(ExternalSearchUsage::query()->count())->toBe($before);
});

it('redirects the legacy external route to the canonical slug', function () {
    $listing = externalListing();

    $this->get(route('external.show', $listing->external_id))
        ->assertRedirect(route('external.detail', $listing->slug))
        ->assertStatus(301);
});

it('allows users to favorite and list external listings', function () {
    $listing = externalListing();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->postJson("/api/favorites/external/{$listing->external_id}")
        ->assertOk()
        ->assertJson(['favorited' => true]);

    $this->assertDatabaseHas('favorites', [
        'user_id' => $user->getKey(),
        'external_listing_id' => $listing->getKey(),
    ]);

    $this->actingAs($user)
        ->get('/panel/favoritos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.type', 'external')
            ->where('items.0.external.id', $listing->external_id)
        );

    $this->actingAs($user)
        ->postJson("/api/favorites/external/{$listing->external_id}")
        ->assertOk()
        ->assertJson(['favorited' => false]);

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $user->getKey(),
        'external_listing_id' => $listing->getKey(),
    ]);
});

it('marks stale external listings and exposes the original link', function () {
    $listing = externalListing(['last_seen_at' => now()->subDays(8)]);

    $this->get(route('external.detail', $listing->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('external.stale', true)
            ->where('external.sourceUrl', $listing->source_url)
        );
});

it('returns external cards with the canonical detail url', function () {
    $externalId = 'feed1234feed1234feed1234feed1234';

    ExternalSearchCache::query()->create([
        'query_key' => 'ext|toyota corolla|mercadolibre',
        'query_text' => 'toyota corolla',
        'platforms' => [['id' => 'mercadolibre', 'name' => 'Mercado Libre Autos', 'color' => '#FFE600', 'initials' => 'ML']],
        'results' => [[
            'id' => $externalId,
            'title' => 'Toyota Corolla 2022',
            'price' => 350000,
            'year' => 2022,
            'km' => 42000,
            'location' => 'Monterrey, NL',
            'imageUrl' => 'https://http2.mlstatic.com/D_NQ_NP_456.jpg',
            'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-987654321-toyota-corolla-2022',
            'platformId' => 'mercadolibre',
            'platformName' => 'Mercado Libre Autos',
        ]],
        'platforms_searched' => ['mercadolibre'],
    ]);

    $this->get('/comprar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('externalCars', 1)
            ->where('externalCars.0.url', fn ($url) => is_string($url) && str_contains($url, '/auto-externo/'))
        );
});
