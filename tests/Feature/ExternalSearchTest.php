<?php

use App\Models\CouponRecipient;
use App\Models\ExternalSearchCache;
use App\Models\Plan;
use App\Models\SearchTokenTransaction;
use App\Models\SearchTokenWallet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use App\Services\ExternalSearch\ExternalPlatformConfig;
use App\Services\ExternalSearch\ExternalSearchCacheService;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\SearchCouponService;
use App\Services\SearchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function externalFirecrawlFake(): void
{
    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => [[
                'url' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
                'json' => [
                    'autos' => [[
                        'title' => 'Toyota Corolla 2020',
                        'price' => 300000,
                        'year' => 2020,
                        'km' => 50000,
                        'location' => 'CDMX',
                        'imageUrl' => 'https://http2.mlstatic.com/corolla.jpg',
                        'sourceUrl' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
                    ]],
                ],
            ]],
        ]),
        '*' => Http::response('', 200),
    ]);
}

function externalTokenPlan(): Plan
{
    return Plan::query()->create([
        'key_name' => 'search_tokens_5_test',
        'type' => 'tokens',
        'name' => '5 tokens test',
        'price' => 49,
        'duration' => 'Sin vencimiento',
        'duration_weeks' => 0,
        'max_cars' => 0,
        'token_amount' => 5,
        'features' => [],
        'sort_order' => 90,
        'is_active' => true,
    ]);
}

function externalCachedCar(): array
{
    return [
        'id' => app(ExternalCarNormalizer::class)->externalId('https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020'),
        'title' => 'Toyota Corolla 2020',
        'price' => 300000,
        'year' => 2020,
        'km' => 50000,
        'location' => 'CDMX',
        'imageUrl' => 'https://http2.mlstatic.com/corolla.jpg',
        'gallery' => [],
        'sourceUrl' => 'https://autos.mercadolibre.com.mx/MLM-123456789-toyota-corolla-2020',
        'platformId' => 'mercadolibre',
        'platformName' => 'Mercado Libre Autos',
        'platformColor' => '#FFE600',
        'platformInitials' => 'ML',
    ];
}

it('applies three free searches and then consumes a token', function () {
    externalFirecrawlFake();
    config()->set('services.firecrawl.key', 'test-key');

    $user = User::factory()->create();
    $this->actingAs($user);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/external-search', ['q' => 'toyota corolla '.$i])
            ->assertOk()
            ->assertJsonPath('limitReached', false);
    }

    $this->postJson('/api/external-search', ['q' => 'toyota corolla 4'])
        ->assertOk()
        ->assertJsonPath('limitReached', true);

    app(SearchTokenService::class)->credit($user, 1, 'purchase', 'test:token');

    $this->postJson('/api/external-search', ['q' => 'toyota corolla 5'])
        ->assertOk()
        ->assertJsonPath('tokenUsed', true);
});

it('uses free views before tokens and hides source urls', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $this->seed();

    $user = User::factory()->create();
    $this->actingAs($user);

    $car = externalCachedCar();

    ExternalSearchCache::query()->create([
        'query_key' => 'test-cache',
        'query_text' => 'toyota',
        'platforms' => [],
        'results' => [$car],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    $this->getJson('/api/external-search/cached')
        ->assertOk()
        ->assertJsonPath('results.0.sourceUrl', '');

    for ($i = 0; $i < 15; $i++) {
        $this->postJson('/api/external-search/resolve', ['id' => $car['id']])
            ->assertOk()
            ->assertJsonPath('allowed', true);
    }

    $this->postJson('/api/external-search/resolve', ['id' => $car['id']])
        ->assertOk()
        ->assertJsonPath('allowed', false)
        ->assertJsonPath('limitReached', true);

    app(SearchTokenService::class)->credit($user, 1, 'purchase', 'test:view-token');

    $this->postJson('/api/external-search/resolve', ['id' => $car['id']])
        ->assertOk()
        ->assertJsonPath('allowed', true);
});

it('gives members unlimited access and source urls', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $this->seed();

    $user = User::factory()->create();
    $plan = Plan::query()->where('key_name', 'membresia_ext_499')->firstOrFail();

    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'membership',
        'plan_id' => $plan->getKey(),
        'car_id' => null,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'amount' => 499,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $car = externalCachedCar();
    ExternalSearchCache::query()->create([
        'query_key' => 'member-cache',
        'query_text' => 'toyota',
        'platforms' => [],
        'results' => [$car],
        'platforms_searched' => ['Mercado Libre Autos'],
    ]);

    $this->actingAs($user)->getJson('/api/external-search/cached')
        ->assertOk()
        ->assertJsonPath('results.0.sourceUrl', $car['sourceUrl']);

    $this->actingAs($user)->postJson('/api/external-search/resolve', ['id' => $car['id']])
        ->assertOk()
        ->assertJsonPath('allowed', true)
        ->assertJsonPath('sourceUrl', $car['sourceUrl']);
});

it('extends an active membership from its current end date', function () {
    $this->seed();

    $user = User::factory()->create();
    $plan = Plan::query()->where('key_name', 'membresia_ext_499')->firstOrFail();
    $previousEnd = now()->addDays(10);

    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'membership',
        'plan_id' => $plan->getKey(),
        'car_id' => null,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'amount' => 499,
        'starts_at' => now()->subDays(20),
        'ends_at' => $previousEnd,
    ]);

    $purchase = app(MembershipService::class)->begin($user);
    app(MembershipService::class)->fulfill($purchase);

    expect($purchase->fresh()->status)->toBe('completed')
        ->and($purchase->fresh()->ends_at?->timestamp)->toBe($previousEnd->copy()->addWeeks(4)->timestamp);
});

it('credits purchased tokens only once', function () {
    $this->seed();

    $user = User::factory()->create();
    $plan = externalTokenPlan();

    $subscription = Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'tokens',
        'plan_id' => $plan->getKey(),
        'car_id' => null,
        'status' => 'pending',
        'billing_cycle' => 'one_time',
        'amount' => 49,
    ]);

    $payment = app(PaymentService::class)->createPayment($subscription, 'stripe', 49, 'pi_test_123');
    app(PaymentService::class)->markPaid($payment);
    app(PaymentService::class)->markPaid($payment);

    expect(SearchTokenWallet::query()->where('user_id', $user->getKey())->value('balance'))->toBe(5)
        ->and(SearchTokenTransaction::query()->where('user_id', $user->getKey())->count())->toBe(1);
});

it('limits the token coupon to authorized emails and token plans', function () {
    $this->seed();

    $user = User::factory()->create(['email' => 'coupon@example.com']);
    $plan = externalTokenPlan();
    $coupon = app(SearchCouponService::class);

    expect($coupon->hasCoupon($user))->toBeFalse();

    CouponRecipient::query()->create([
        'email' => 'coupon@example.com',
        'coupon_code' => 'TKNS50',
    ]);

    expect($coupon->hasCoupon($user))->toBeTrue()
        ->and($coupon->amount($plan, $user, 'TKNS50'))->toBe(24.5)
        ->and($coupon->amount($plan, $user, 'OTHER'))->toBe(49.0);
});

it('filters irrelevant brands or years and aggregates modern platform urls across json, openai and markdown fallback', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-firecrawl');
    config()->set('services.openai.key', 'test-openai');

    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (! str_contains($url, 'api.firecrawl.dev') && ! str_contains($url, 'api.openai.com')) {
            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '.jpg')) {
                return Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('<html><body>Toyota 2025 disponible en venta</body></html>', 200, ['Content-Type' => 'text/html']);
        }

        if (str_contains($url, 'api.openai.com')) {
            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'cars' => [
                                [
                                    'title' => 'Ford Territory Titanium 2025',
                                    'price' => 590000,
                                    'year' => 2025,
                                    'km' => 12000,
                                    'location' => 'Monterrey',
                                    'imageUrl' => 'https://cdn.kavak.services/ford.jpg',
                                    'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/ford-territory-2025-id-11111',
                                ],
                                [
                                    'title' => 'Toyota RAV4 XLE 2025',
                                    'price' => 645000,
                                    'year' => 2025,
                                    'km' => 8000,
                                    'location' => 'Guadalajara',
                                    'imageUrl' => 'https://cdn.kavak.services/rav4.jpg',
                                    'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/toyota-rav4-2025-id-22222',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]);
        }

        $domains = $request['includeDomains'] ?? [];

        if (in_array('auto.mercadolibre.com.mx', $domains, true)) {
            return Http::response([
                'data' => [[
                    'url' => 'https://auto.mercadolibre.com.mx/MLM-3344556677-toyota-corolla-2025-_JM',
                    'metadata' => ['ogImage' => 'https://http2.mlstatic.com/D_NQ_NP_corolla.jpg'],
                    'json' => [
                        'autos' => [
                            [
                                'title' => 'Ford Mustang GT 2025',
                                'price' => 980000,
                                'year' => 2025,
                                'imageUrl' => 'https://http2.mlstatic.com/mustang.jpg',
                                'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-99999911-ford-mustang-2025-_JM',
                            ],
                            [
                                'title' => 'Toyota Corolla 2022',
                                'price' => 330000,
                                'year' => 2022,
                                'imageUrl' => 'https://http2.mlstatic.com/corolla-2022.jpg',
                                'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-88888811-toyota-corolla-2022-_JM',
                            ],
                            [
                                'title' => 'Toyota Corolla LE 2025',
                                'price' => 455000,
                                'year' => 2025,
                                'km' => 5000,
                                'location' => 'CDMX',
                                'imageUrl' => '',
                                'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-3344556677-toyota-corolla-2025-_JM',
                            ],
                        ],
                    ],
                    'markdown' => 'Toyota Corolla LE 2025 en CDMX $455,000',
                ]],
            ]);
        }

        if (in_array('seminuevos.com', $domains, true)) {
            return Http::response([
                'data' => [[
                    'url' => 'https://www.seminuevos.com/vehicle/autos-toyota-camry-2025-9876543',
                    'metadata' => ['ogImage' => 'https://images.latamautos.com/thumbs/camry-2025-9876543.jpg'],
                    'images' => ['https://images.latamautos.com/thumbs/camry-2025-9876543.jpg'],
                    'markdown' => implode("\n", [
                        '[Toyota Camry XSE 2025](https://www.seminuevos.com/vehicle/autos-toyota-camry-2025-9876543)',
                        'Precio $615,000 MXN · 10,500 km · Monterrey',
                    ]),
                ]],
            ]);
        }

        if (in_array('kavak.com', $domains, true)) {
            return Http::response([
                'data' => [[
                    'url' => 'https://www.kavak.com/mx/seminuevos/toyota-2025',
                    'links' => [
                        'https://www.kavak.com/mx/seminuevos/toyota-yaris-2025-id-77777',
                        'https://www.kavak.com/mx/seminuevos/toyota-rav4-2025-id-22222',
                        'https://www.kavak.com/mx/seminuevos/ford-territory-2025-id-11111',
                    ],
                    'images' => [
                        'https://cloudfront.net/yaris-2025.jpg',
                        'https://cdn.kavak.services/rav4.jpg',
                    ],
                    'json' => [
                        'autos' => [
                            [
                                'title' => 'Toyota Yaris S CVT 2025',
                                'price' => 365000,
                                'year' => 2025,
                                'imageUrl' => 'https://cloudfront.net/yaris-2025.jpg',
                                'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/toyota-yaris-2025-id-77777',
                            ],
                        ],
                    ],
                    'markdown' => 'Listado Toyota RAV4 XLE 2025 y Toyota Yaris S CVT 2025',
                ]],
            ]);
        }

        return Http::response([
            'data' => [[
                'url' => 'https://www.autocosmos.com.mx/auto/nuevo/toyota/prius/2025',
                'links' => ['https://www.autocosmos.com.mx/auto/nuevo/toyota/prius/2025-hevc'],
                'images' => ['https://s3.amazonaws.com/bucket/prius-2025.jpg'],
                'json' => [
                    'autos' => [[
                        'title' => 'Toyota Prius Base 2025',
                        'price' => 485000,
                        'year' => 2025,
                        'imageUrl' => 'https://s3.amazonaws.com/bucket/prius-2025.jpg',
                        'sourceUrl' => 'https://www.autocosmos.com.mx/auto/nuevo/toyota/prius/2025-hevc',
                    ]],
                ],
                'markdown' => 'Toyota Prius Base 2025 $485,000',
            ]],
        ]);
    });

    $response = $this->postJson('/api/external-search', ['q' => 'toyota 2025'])
        ->assertOk();

    $titles = collect($response->json('results'))->pluck('title')->all();

    expect($response->json('results'))->toHaveCount(5)
        ->and($titles)->toContain('Toyota Corolla LE 2025')
        ->and($titles)->toContain('Toyota RAV4 XLE 2025')
        ->and($titles)->toContain('Toyota Yaris S CVT 2025')
        ->and($titles)->toContain('Toyota Camry XSE 2025')
        ->and($titles)->toContain('Toyota Prius Base 2025')
        ->and(collect($titles)->filter(fn (string $title) => str_contains($title, 'Ford') || str_contains($title, '2022')))->toBeEmpty()
        ->and(collect($response->json('results'))->firstWhere('title', 'Toyota Corolla LE 2025')['imageUrl'])
        ->toBe('https://http2.mlstatic.com/D_NQ_NP_corolla.jpg');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.firecrawl.dev')
        && ($request['country'] ?? null) === 'MX'
        && ($request['location'] ?? null) === 'Mexico');
});

it('does not persist sparse results for 7 days and supports refresh', function () {
    Cache::flush();
    config()->set('services.firecrawl.key', 'test-firecrawl');
    config()->set('services.openai.key', null);

    $user = User::factory()->create();
    $this->actingAs($user);

    $attempt = 0;

    Http::fake(function (Request $request) use (&$attempt) {
        $url = $request->url();

        if (! str_contains($url, 'api.firecrawl.dev')) {
            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '.jpg')) {
                return Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('<html><body>Toyota 2025 disponible</body></html>', 200, ['Content-Type' => 'text/html']);
        }

        $attempt++;

        if ($attempt <= 4) {
            return Http::response([
                'data' => [[
                    'url' => 'https://auto.mercadolibre.com.mx/MLM-10000101-toyota-hilux-2025-_JM',
                    'json' => [
                        'autos' => [[
                            'title' => 'Toyota Hilux 2025',
                            'price' => 590000,
                            'year' => 2025,
                            'imageUrl' => 'https://http2.mlstatic.com/hilux.jpg',
                            'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-10000101-toyota-hilux-2025-_JM',
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response([
            'data' => [[
                'url' => 'https://www.kavak.com/mx/seminuevos/toyota-2025',
                'links' => [
                    'https://www.kavak.com/mx/seminuevos/toyota-corolla-2025-id-101',
                    'https://www.kavak.com/mx/seminuevos/toyota-camry-2025-id-102',
                    'https://www.kavak.com/mx/seminuevos/toyota-sienna-2025-id-103',
                ],
                'images' => [
                    'https://cdn.kavak.services/c1.jpg',
                    'https://cdn.kavak.services/c2.jpg',
                    'https://cdn.kavak.services/c3.jpg',
                ],
                'json' => [
                    'autos' => [
                        [
                            'title' => 'Toyota Corolla 2025',
                            'price' => 450000,
                            'year' => 2025,
                            'imageUrl' => 'https://cdn.kavak.services/c1.jpg',
                            'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/toyota-corolla-2025-id-101',
                        ],
                        [
                            'title' => 'Toyota Camry 2025',
                            'price' => 610000,
                            'year' => 2025,
                            'imageUrl' => 'https://cdn.kavak.services/c2.jpg',
                            'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/toyota-camry-2025-id-102',
                        ],
                        [
                            'title' => 'Toyota Sienna 2025',
                            'price' => 890000,
                            'year' => 2025,
                            'imageUrl' => 'https://cdn.kavak.services/c3.jpg',
                            'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/toyota-sienna-2025-id-103',
                        ],
                    ],
                ],
            ]],
        ]);
    });

    $first = $this->postJson('/api/external-search', ['q' => 'toyota 2025'])->assertOk();
    expect($first->json('results'))->toHaveCount(1)
        ->and(ExternalSearchCache::query()->count())->toBe(0);

    $second = $this->postJson('/api/external-search', ['q' => 'toyota 2025', 'refresh' => true])->assertOk();
    expect($second->json('results'))->toHaveCount(3)
        ->and(ExternalSearchCache::query()->count())->toBe(1);
});

it('rejects hallucinated placeholder domains, dead 404 or non-image photos, and paused or missing listing links', function () {
    Cache::flush();
    $this->seed();
    config()->set('services.firecrawl.key', 'test-firecrawl');
    config()->set('services.openai.key', 'test-openai');

    $user = User::factory()->create();
    $plan = Plan::query()->where('key_name', 'membresia_ext_499')->firstOrFail();

    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'membership',
        'plan_id' => $plan->getKey(),
        'car_id' => null,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'amount' => 499,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($user);

    // Simula un caché envenenado antiguo con example.com y /p/MLM123456: debe ignorarse al leer.
    $normalizer = app(ExternalCarNormalizer::class);
    $cacheService = app(ExternalSearchCacheService::class);
    $activePlatforms = app(ExternalPlatformConfig::class)->active();
    $poisonedKey = $cacheService->key('toyota 2026', $activePlatforms);
    $poisonedResults = [[
        'id' => $normalizer->externalId('https://www.mercadolibre.com.mx/toyota-corolla-2026/p/MLM123456'),
        'title' => 'Toyota Corolla 2026',
        'price' => 350000,
        'year' => 2026,
        'imageUrl' => 'https://example.com/images/toyota-corolla-2026.jpg',
        'sourceUrl' => 'https://www.mercadolibre.com.mx/toyota-corolla-2026/p/MLM123456',
        'platformId' => 'mercadolibre',
        'platformName' => 'Mercado Libre Autos',
    ]];

    Cache::put('external-search:'.$poisonedKey, [
        'results' => $poisonedResults,
        'platformsSearched' => ['Mercado Libre Autos'],
    ], now()->addMinutes(30));

    $cacheService->put(
        $poisonedKey,
        'toyota 2026',
        $activePlatforms,
        $poisonedResults,
        ['Mercado Libre Autos'],
    );

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'api.openai.com')) {
            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'cars' => [
                                [
                                    'title' => 'Toyota Corolla Alucinado 2026',
                                    'price' => 350000,
                                    'year' => 2026,
                                    'imageUrl' => 'https://example.com/images/toyota-corolla-2026.jpg',
                                    'sourceUrl' => 'https://www.mercadolibre.com.mx/toyota-corolla-2026/p/MLM123456',
                                ],
                                [
                                    'title' => 'Toyota Camry SinFotoViva 2026',
                                    'price' => 620000,
                                    'year' => 2026,
                                    'imageUrl' => 'https://images.latamautos.com/dead-404.jpg',
                                    'sourceUrl' => 'https://www.seminuevos.com/vehicle/autos-toyota-camry-2026/4840001',
                                ],
                                [
                                    'title' => 'Toyota Sienna HtmlEnImagen 2026',
                                    'price' => 990000,
                                    'year' => 2026,
                                    'imageUrl' => 'https://images.latamautos.com/html-page.jpg',
                                    'sourceUrl' => 'https://www.seminuevos.com/vehicle/autos-toyota-sienna-2026/4840002',
                                ],
                                [
                                    'title' => 'Toyota Tacoma LinkPausado 2026',
                                    'price' => 950000,
                                    'year' => 2026,
                                    'imageUrl' => 'https://images.latamautos.com/tacoma-ok.jpg',
                                    'sourceUrl' => 'https://www.seminuevos.com/vehicle/autos-toyota-tacoma-2026/4840003',
                                ],
                                [
                                    'title' => 'Toyota Corolla RealVerificado 2026',
                                    'price' => 540000,
                                    'year' => 2026,
                                    'km' => 1200,
                                    'location' => 'CDMX',
                                    'imageUrl' => 'https://images.latamautos.com/corolla-real.jpg',
                                    'sourceUrl' => 'https://www.seminuevos.com/vehicle/autos-toyota-corolla-2026/4840004',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]);
        }

        if (str_contains($url, 'api.firecrawl.dev')) {
            $domains = $request['includeDomains'] ?? [];

            if (in_array('seminuevos.com', $domains, true)) {
                return Http::response([
                    'data' => [[
                        'url' => 'https://www.seminuevos.com/autos/toyota-2026',
                        'links' => [
                            'https://www.seminuevos.com/vehicle/autos-toyota-camry-2026/4840001',
                            'https://www.seminuevos.com/vehicle/autos-toyota-sienna-2026/4840002',
                            'https://www.seminuevos.com/vehicle/autos-toyota-tacoma-2026/4840003',
                            'https://www.seminuevos.com/vehicle/autos-toyota-corolla-2026/4840004',
                        ],
                        'images' => [
                            'https://images.latamautos.com/dead-404.jpg',
                            'https://images.latamautos.com/html-page.jpg',
                            'https://images.latamautos.com/tacoma-ok.jpg',
                            'https://images.latamautos.com/corolla-real.jpg',
                        ],
                        'markdown' => 'Toyota Camry, Sienna, Tacoma y Corolla 2026 en venta',
                    ]],
                ]);
            }

            return Http::response(['data' => []]);
        }

        return match ($url) {
            'https://images.latamautos.com/dead-404.jpg' => Http::response('Not found', 404),
            'https://images.latamautos.com/html-page.jpg' => Http::response('<html>Blocked</html>', 200, ['Content-Type' => 'text/html']),
            'https://images.latamautos.com/tacoma-ok.jpg',
            'https://images.latamautos.com/corolla-real.jpg' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            'https://www.seminuevos.com/vehicle/autos-toyota-tacoma-2026/4840003' => Http::response('<html>Publicación pausada</html>', 200, ['Content-Type' => 'text/html']),
            'https://www.seminuevos.com/vehicle/autos-toyota-camry-2026/4840001',
            'https://www.seminuevos.com/vehicle/autos-toyota-sienna-2026/4840002',
            'https://www.seminuevos.com/vehicle/autos-toyota-corolla-2026/4840004' => Http::response('<html>Toyota Corolla 2026 en venta</html>', 200, ['Content-Type' => 'text/html']),
            default => Http::response('', 404),
        };
    });

    expect($normalizer->isDetailUrl('https://www.mercadolibre.com.mx/toyota-corolla-2026/p/MLM123456', 'mercadolibre'))->toBeFalse()
        ->and($normalizer->isValidImage('https://example.com/images/toyota-corolla-2026.jpg', 'mercadolibre'))->toBeFalse();

    $response = $this->postJson('/api/external-search', ['q' => 'toyota 2026'])->assertOk();

    expect($response->json('results'))->toHaveCount(1)
        ->and($response->json('results.0.title'))->toBe('Toyota Corolla RealVerificado 2026')
        ->and($response->json('results.0.imageUrl'))->toBe('https://images.latamautos.com/corolla-real.jpg')
        ->and($response->json('results.0.sourceUrl'))->toBe('https://www.seminuevos.com/vehicle/autos-toyota-corolla-2026/4840004');
});

it('rejects listing pages, banners, kavak base64 svg placeholders and null-price stubs while returning balanced individual vehicles', function () {
    $normalizer = app(ExternalCarNormalizer::class);

    $kavakSvgPayload = rtrim(strtr(base64_encode('{"key":"assets/images/brands/color/kia.svg"}'), '+/', '-_'), '=');
    $kavakCarPhotoPayload = static fn (string $carId): string => rtrim(
        strtr(base64_encode('{"bucket":"kavak-prd","key":"images/'.$carId.'/exterior-01.jpg"}'), '+/', '-_'),
        '=',
    );

    $kavakBrandSvgUrl = 'https://images.prd.kavak.io//'.$kavakSvgPayload;
    $kavakCarPhoto518519 = 'https://images.prd.kavak.io//'.$kavakCarPhotoPayload('518519');
    $kavakCarPhoto518520 = 'https://images.prd.kavak.io//'.$kavakCarPhotoPayload('518520');
    $kavakCarPhoto518521 = 'https://images.prd.kavak.io//'.$kavakCarPhotoPayload('518521');
    $kavakCarPhoto518522 = 'https://images.prd.kavak.io//'.$kavakCarPhotoPayload('518522');

    expect($normalizer->isDetailUrl('https://www.seminuevos.com/usados/-/autos/-/ford/-/2024', 'seminuevos'))->toBeFalse()
        ->and($normalizer->isDetailUrl('https://www.kavak.com/mx/seminuevos/ford/2024', 'kavak'))->toBeFalse()
        ->and($normalizer->isDetailUrl('https://www.kavak.com/mx/seminuevos/ford/f-150/2024', 'kavak'))->toBeFalse()
        ->and($normalizer->isDetailUrl('https://www.kavak.com/mx/seminuevos/ford/f-150-xlt-2024-id-518519', 'kavak'))->toBeTrue()
        ->and($normalizer->isDetailUrl('https://www.seminuevos.com/vehicle/autos-ford-bronco-sport-2024/4845136', 'seminuevos'))->toBeTrue()
        ->and($normalizer->isValidImage('https://static.seminuevos.com/banners/seminuevos-og-default.jpg', 'seminuevos'))->toBeFalse()
        ->and($normalizer->isValidImage('https://www.kavak.com/images/financing-banner-sm.webp', 'kavak'))->toBeFalse()
        ->and($normalizer->isValidImage('https://www.seminuevos.com/_next/image?url=https%3A%2F%2Fstatic.seminuevos.com%2Fads%2FSeminuevos_Tarjeta.png&w=3840&q=75', 'seminuevos'))->toBeFalse()
        ->and($normalizer->isValidImage($kavakBrandSvgUrl, 'kavak'))->toBeFalse()
        ->and($normalizer->isValidImage($kavakCarPhoto518519, 'kavak'))->toBeTrue();

    config()->set('services.firecrawl.key', 'fc-test-key');
    config()->set('services.openai.key', 'oa-test-key');
    config()->set('external_search.active_platforms', ['mercadolibre', 'seminuevos', 'kavak', 'autocosmos']);

    $seminuevosCards = [
        ['id' => '4845136', 'title' => 'Ford Bronco Sport Big Bend 2024', 'price' => 549000, 'km' => 18500, 'slug' => 'autos-ford-bronco-sport-cdmx-2024'],
        ['id' => '4845206', 'title' => 'Ford Territory Titanium 2024', 'price' => 515000, 'km' => 22100, 'slug' => 'autos-ford-territory-monterrey-2024'],
        ['id' => '4844459', 'title' => 'Ford Escape Active Hybrid 2024', 'price' => 610000, 'km' => 14000, 'slug' => 'autos-ford-escape-guadalajara-2024'],
        ['id' => '4843129', 'title' => 'Ford Ranger XLT 2024', 'price' => 685000, 'km' => 19200, 'slug' => 'autos-ford-ranger-queretaro-2024'],
    ];

    Http::fake(function (Request $request) use (
        $kavakBrandSvgUrl,
        $kavakCarPhoto518519,
        $kavakCarPhoto518520,
        $kavakCarPhoto518521,
        $kavakCarPhoto518522,
        $seminuevosCards,
    ) {
        $url = $request->url();

        if (str_contains($url, 'api.openai.com')) {
            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'autos' => [
                                [
                                    'title' => 'Autos Ford 2024 usados en venta',
                                    'price' => 435000,
                                    'year' => 2024,
                                    'imageUrl' => 'https://static.seminuevos.com/banners/seminuevos-og-default.jpg',
                                    'sourceUrl' => 'https://www.seminuevos.com/usados/-/autos/-/ford/-/2024',
                                ],
                                [
                                    'title' => '2024',
                                    'price' => null,
                                    'year' => 2024,
                                    'imageUrl' => 'https://www.kavak.com/images/financing-banner-sm.webp',
                                    'sourceUrl' => 'https://www.kavak.com/mx/seminuevos/ford/f-150/2024',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ]);
        }

        if (str_contains($url, 'api.firecrawl.dev/v2/search')) {
            $domains = $request['includeDomains'] ?? [];

            if (in_array('seminuevos.com', $domains, true)) {
                $markdownLines = [];
                $links = [];
                $images = [
                    'https://static.seminuevos.com/banners/seminuevos-og-default.jpg',
                    'https://www.seminuevos.com/_next/image?url=https%3A%2F%2Fstatic.seminuevos.com%2Fads%2FSeminuevos_Tarjeta.png&w=3840&q=75',
                ];

                foreach ($seminuevosCards as $card) {
                    $detailPath = "/vehicle/{$card['slug']}/{$card['id']}";
                    $photoUrl = "https://images.latamautos.com/thumbs/w/sm/ptx_migration_mexico/27102026/{$card['id']}/o_o/{$card['id']}_photo.jpg";
                    $links[] = 'https://www.seminuevos.com'.$detailPath;
                    $images[] = $photoUrl;
                    $markdownLines[] = "[{$card['title']}]({$detailPath})";
                    $markdownLines[] = '$'.number_format($card['price']).' · '.number_format($card['km']).' km';
                    $markdownLines[] = '';
                }

                return Http::response([
                    'data' => [[
                        'url' => 'https://www.seminuevos.com/usados/-/autos/-/ford/-/2024',
                        'title' => 'Autos Ford 2024 usados en venta',
                        'metadata' => [
                            'ogImage' => 'https://static.seminuevos.com/banners/seminuevos-og-default.jpg',
                        ],
                        'links' => $links,
                        'images' => $images,
                        'markdown' => implode("\n", $markdownLines),
                    ]],
                ]);
            }

            if (in_array('kavak.com', $domains, true)) {
                return Http::response([
                    'data' => [[
                        'url' => 'https://www.kavak.com/mx/seminuevos/ford/f-150/2024',
                        'title' => '2024',
                        'metadata' => [
                            'ogImage' => 'https://www.kavak.com/images/financing-banner-sm.webp',
                        ],
                        'links' => [
                            'https://www.kavak.com/mx/seminuevos/ford/f-150-xlt-2024-id-518519',
                            'https://www.kavak.com/mx/seminuevos/ford/maverick-lariat-2024-id-518520',
                            'https://www.kavak.com/mx/seminuevos/ford/territory-titanium-2024-id-518521',
                            'https://www.kavak.com/mx/seminuevos/ford/lobo-platinum-2024-id-518522',
                        ],
                        'images' => [
                            'https://www.kavak.com/images/financing-banner-sm.webp',
                            $kavakBrandSvgUrl,
                            $kavakCarPhoto518519,
                            $kavakCarPhoto518520,
                            $kavakCarPhoto518521,
                            $kavakCarPhoto518522,
                        ],
                        'markdown' => implode("\n", [
                            '[Ford F-150 XLT 2024](/mx/seminuevos/ford/f-150-xlt-2024-id-518519)',
                            '$720,000 · 15,000 km',
                            '',
                            '[Ford Maverick Lariat 2024](/mx/seminuevos/ford/maverick-lariat-2024-id-518520)',
                            '$645,000 · 11,400 km',
                            '',
                            '[Ford Territory Titanium 2024](/mx/seminuevos/ford/territory-titanium-2024-id-518521)',
                            '$498,000 · 24,500 km',
                            '',
                            '[Ford Lobo Platinum 2024](/mx/seminuevos/ford/lobo-platinum-2024-id-518522)',
                            '$1,090,000 · 9,800 km',
                        ]),
                    ]],
                ]);
            }

            if (in_array('autocosmos.com.mx', $domains, true)) {
                return Http::response([
                    'data' => [
                        [
                            'url' => 'https://www.autocosmos.com.mx/auto/usado/ford/territory/titanium/97cf72db82c84872a2724694d0743b50',
                            'images' => ['https://cdn.autocosmos.com.mx/ford-territory-2024.jpg'],
                            'json' => [
                                'autos' => [[
                                    'title' => 'Ford Territory Titanium 2024',
                                    'price' => 469000,
                                    'year' => 2024,
                                    'km' => 52584,
                                    'imageUrl' => 'https://cdn.autocosmos.com.mx/ford-territory-2024.jpg',
                                    'sourceUrl' => 'https://www.autocosmos.com.mx/auto/usado/ford/territory/titanium/97cf72db82c84872a2724694d0743b50',
                                ]],
                            ],
                            'markdown' => 'Ford Territory Titanium 2024 $469,000 52,584 km',
                        ],
                        [
                            'url' => 'https://www.autocosmos.com.mx/auto/usado/ford/explorer/limited/88cf72db82c84872a2724694d0743b51',
                            'images' => ['https://cdn.autocosmos.com.mx/ford-explorer-2024.jpg'],
                            'json' => [
                                'autos' => [[
                                    'title' => 'Ford Explorer Limited 2024',
                                    'price' => 890000,
                                    'year' => 2024,
                                    'km' => 19300,
                                    'imageUrl' => 'https://cdn.autocosmos.com.mx/ford-explorer-2024.jpg',
                                    'sourceUrl' => 'https://www.autocosmos.com.mx/auto/usado/ford/explorer/limited/88cf72db82c84872a2724694d0743b51',
                                ]],
                            ],
                            'markdown' => 'Ford Explorer Limited 2024 $890,000 19,300 km',
                        ],
                        [
                            'url' => 'https://www.autocosmos.com.mx/auto/usado/ford/edge/st-line/77cf72db82c84872a2724694d0743b52',
                            'images' => ['https://cdn.autocosmos.com.mx/ford-edge-2024.jpg'],
                            'json' => [
                                'autos' => [[
                                    'title' => 'Ford Edge ST-Line 2024',
                                    'price' => 765000,
                                    'year' => 2024,
                                    'km' => 16500,
                                    'imageUrl' => 'https://cdn.autocosmos.com.mx/ford-edge-2024.jpg',
                                    'sourceUrl' => 'https://www.autocosmos.com.mx/auto/usado/ford/edge/st-line/77cf72db82c84872a2724694d0743b52',
                                ]],
                            ],
                            'markdown' => 'Ford Edge ST-Line 2024 $765,000 16,500 km',
                        ],
                        [
                            'url' => 'https://www.autocosmos.com.mx/auto/usado/ford/mustang/gt/66cf72db82c84872a2724694d0743b53',
                            'images' => ['https://cdn.autocosmos.com.mx/ford-mustang-2024.jpg'],
                            'json' => [
                                'autos' => [[
                                    'title' => 'Ford Mustang GT 2024',
                                    'price' => 980000,
                                    'year' => 2024,
                                    'km' => 6400,
                                    'imageUrl' => 'https://cdn.autocosmos.com.mx/ford-mustang-2024.jpg',
                                    'sourceUrl' => 'https://www.autocosmos.com.mx/auto/usado/ford/mustang/gt/66cf72db82c84872a2724694d0743b53',
                                ]],
                            ],
                            'markdown' => 'Ford Mustang GT 2024 $980,000 6,400 km',
                        ],
                    ],
                ]);
            }

            if (in_array('auto.mercadolibre.com.mx', $domains, true)) {
                return Http::response([
                    'data' => [[
                        'url' => 'https://listado.mercadolibre.com.mx/autos-camionetas/ford/2024',
                        'links' => [
                            'https://auto.mercadolibre.com.mx/MLM-3000001-ford-focus-2024-_JM',
                            'https://auto.mercadolibre.com.mx/MLM-3000002-ford-transit-2024-_JM',
                            'https://auto.mercadolibre.com.mx/MLM-3000003-ford-maverick-2024-_JM',
                            'https://auto.mercadolibre.com.mx/MLM-3000004-ford-bronco-2024-_JM',
                        ],
                        'images' => [
                            'https://http2.mlstatic.com/D_NQ_NP_3000001.jpg',
                            'https://http2.mlstatic.com/D_NQ_NP_3000002.jpg',
                            'https://http2.mlstatic.com/D_NQ_NP_3000003.jpg',
                            'https://http2.mlstatic.com/D_NQ_NP_3000004.jpg',
                        ],
                        'json' => [
                            'autos' => [
                                ['title' => 'Ford Focus 2024', 'price' => 410000, 'year' => 2024, 'imageUrl' => 'https://http2.mlstatic.com/D_NQ_NP_3000001.jpg', 'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-3000001-ford-focus-2024-_JM'],
                                ['title' => 'Ford Transit 2024', 'price' => 795000, 'year' => 2024, 'imageUrl' => 'https://http2.mlstatic.com/D_NQ_NP_3000002.jpg', 'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-3000002-ford-transit-2024-_JM'],
                                ['title' => 'Ford Maverick 2024', 'price' => 625000, 'year' => 2024, 'imageUrl' => 'https://http2.mlstatic.com/D_NQ_NP_3000003.jpg', 'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-3000003-ford-maverick-2024-_JM'],
                                ['title' => 'Ford Bronco 2024', 'price' => 940000, 'year' => 2024, 'imageUrl' => 'https://http2.mlstatic.com/D_NQ_NP_3000004.jpg', 'sourceUrl' => 'https://auto.mercadolibre.com.mx/MLM-3000004-ford-bronco-2024-_JM'],
                            ],
                        ],
                        'markdown' => 'Ford 2024 en Mercado Libre',
                    ]],
                ]);
            }

            return Http::response(['data' => []]);
        }

        if (str_contains($url, 'images.prd.kavak.io') || str_contains($url, 'images.latamautos.com') || str_contains($url, 'cdn.autocosmos.com.mx') || str_contains($url, 'mlstatic.com')) {
            return Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']);
        }

        return Http::response('<html>Ford 2024 disponible</html>', 200, ['Content-Type' => 'text/html']);
    });

    $admin = User::factory()->create(['email_verified_at' => now()]);
    Role::findOrCreate('superadmin', 'web');
    $admin->assignRole('superadmin');

    $response = $this->actingAs($admin)
        ->postJson('/api/external-search', ['q' => 'ford 2024'])
        ->assertOk();

    $results = collect($response->json('results'));

    expect($results)->toHaveCount(15)
        ->and($results->pluck('platformId')->unique()->all())->toEqualCanonicalizing(['mercadolibre', 'seminuevos', 'kavak', 'autocosmos']);

    foreach ($results as $car) {
        expect($car['price'])->toBeInt()->toBeGreaterThan(25000)
            ->and($car['year'])->toBe(2024)
            ->and($car['imageUrl'])->toStartWith('https://')
            ->and($car['imageUrl'])->not->toContain('seminuevos-og-default')
            ->and($car['imageUrl'])->not->toContain('financing-banner')
            ->and($car['imageUrl'])->not->toContain($kavakSvgPayload)
            ->and($car['sourceUrl'])->not->toContain('/usados/-/')
            ->and(preg_match('/\/2024\/?$/', $car['sourceUrl']))->toBe(0);
    }
});

it('hydrates individual detail links via firecrawl scrape when listing pages lack inline card prices', function () {
    config()->set('services.firecrawl.key', 'fc-test-key');
    config()->set('services.openai.key', null);

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'api.firecrawl.dev/v2/search')) {
            $domains = $request['includeDomains'] ?? [];

            if (in_array('seminuevos.com', $domains, true)) {
                return Http::response([
                    'data' => [[
                        'url' => 'https://www.seminuevos.com/usados/-/autos/-/ford/-/2024',
                        'title' => 'Autos Ford 2024 usados en venta',
                        'metadata' => ['ogImage' => 'https://static.seminuevos.com/banners/seminuevos-og-default.jpg'],
                        'links' => [
                            'https://www.seminuevos.com/vehicle/autos-ford-mustang-cdmx-2024/4845901',
                            'https://www.seminuevos.com/vehicle/autos-ford-maverick-cdmx-2024/4845902',
                            'https://www.seminuevos.com/vehicle/autos-toyota-corolla-cdmx-2024/4845999',
                        ],
                        'images' => ['https://static.seminuevos.com/banners/seminuevos-og-default.jpg'],
                        'markdown' => 'Explora autos usados Ford 2024 en venta en México',
                    ]],
                ]);
            }

            if (in_array('kavak.com', $domains, true)) {
                return Http::response([
                    'data' => [[
                        'url' => 'https://www.kavak.com/mx/seminuevos/ford/2024',
                        'title' => '2024',
                        'metadata' => ['ogImage' => 'https://www.kavak.com/images/financing-banner-sm.webp'],
                        'links' => [
                            'https://www.kavak.com/mx/seminuevos/ford/explorer-xlt-2024-id-519001',
                        ],
                        'images' => ['https://www.kavak.com/images/financing-banner-sm.webp'],
                        'markdown' => 'Catálogo Kavak Ford 2024',
                    ]],
                ]);
            }

            return Http::response(['data' => []]);
        }

        if (str_contains($url, 'api.firecrawl.dev/v2/scrape')) {
            $targetUrl = (string) ($request['url'] ?? '');

            if (str_contains($targetUrl, '4845901')) {
                return Http::response([
                    'data' => [
                        'url' => $targetUrl,
                        'title' => 'Ford Mustang EcoBoost 2024 | Seminuevos.com',
                        'metadata' => ['ogImage' => 'https://images.latamautos.com/thumbs/mustang-4845901.jpg'],
                        'images' => ['https://images.latamautos.com/thumbs/mustang-4845901.jpg'],
                        'markdown' => "# Ford Mustang EcoBoost 2024\nPrecio \$745,000 MXN\n14,200 km · CDMX",
                    ],
                ]);
            }

            if (str_contains($targetUrl, '4845902')) {
                return Http::response([
                    'data' => [
                        'url' => $targetUrl,
                        'title' => 'Ford Maverick Lariat 2024 | Seminuevos.com',
                        'metadata' => ['ogImage' => 'https://images.latamautos.com/thumbs/maverick-4845902.jpg'],
                        'images' => ['https://images.latamautos.com/thumbs/maverick-4845902.jpg'],
                        'markdown' => "# Ford Maverick Lariat 2024\nPrecio \$635,000 MXN\n11,800 km · Monterrey",
                    ],
                ]);
            }

            if (str_contains($targetUrl, 'id-519001')) {
                return Http::response([
                    'data' => [
                        'url' => $targetUrl,
                        'title' => 'Ford Explorer XLT 2024 | Kavak México',
                        'images' => ['https://cdn.kavak.services/ford-explorer-519001.jpg'],
                        'markdown' => "# Ford Explorer XLT 2024\nPrecio \$1,090,000 MXN\n18,400 km",
                    ],
                ]);
            }

            return Http::response(['data' => []], 404);
        }

        if (str_contains($url, 'mustang-4845901.jpg') || str_contains($url, 'maverick-4845902.jpg') || str_contains($url, 'ford-explorer-519001.jpg')) {
            return Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']);
        }

        return Http::response('<html>Ford 2024 disponible</html>', 200, ['Content-Type' => 'text/html']);
    });

    $admin = User::factory()->create(['email_verified_at' => now()]);
    Role::findOrCreate('superadmin', 'web');
    $admin->assignRole('superadmin');

    $response = $this->actingAs($admin)
        ->postJson('/api/external-search', ['q' => 'ford 2024'])
        ->assertOk();

    $results = collect($response->json('results'));

    expect($results)->toHaveCount(3)
        ->and($results->pluck('title')->all())->toEqualCanonicalizing([
            'Ford Mustang EcoBoost 2024',
            'Ford Maverick Lariat 2024',
            'Ford Explorer XLT 2024',
        ]);

    $resolvedUrls = $results
        ->map(fn (array $car): string => (string) $this->postJson('/api/external-search/resolve', ['id' => $car['id']])
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->json('sourceUrl'))
        ->all();

    expect($resolvedUrls)->toEqualCanonicalizing([
        'https://www.seminuevos.com/vehicle/autos-ford-mustang-cdmx-2024/4845901',
        'https://www.seminuevos.com/vehicle/autos-ford-maverick-cdmx-2024/4845902',
        'https://www.kavak.com/mx/seminuevos/ford/explorer-xlt-2024-id-519001',
    ]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.firecrawl.dev/v2/scrape')
        && str_contains((string) ($request['url'] ?? ''), 'toyota-corolla'));
});
