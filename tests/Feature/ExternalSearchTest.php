<?php

use App\Models\CouponRecipient;
use App\Models\ExternalSearchCache;
use App\Models\Plan;
use App\Models\SearchTokenTransaction;
use App\Models\SearchTokenWallet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ExternalSearch\ExternalCarNormalizer;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\SearchCouponService;
use App\Services\SearchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
        ->and($purchase->fresh()->ends_at?->timestamp)->toBe($previousEnd->copy()->addDays(30)->timestamp);
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
