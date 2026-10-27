<?php

use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\PushSubscription;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function paymentUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function paymentPlan(): Plan
{
    return Plan::query()->create([
        'key_name' => 'plan_pago_test',
        'name' => 'Plan Pago Test',
        'price' => 1000,
        'duration' => '30 días',
        'duration_weeks' => 4,
        'max_cars' => 1,
        'is_active' => true,
    ]);
}

function paymentCar(User $user, string $statusName = 'pendiente'): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => $statusName], ['label' => ucfirst($statusName)]);

    return Car::query()->create([
        'owner_id' => $user->getKey(),
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $status->getKey(),
        'slug' => 'payment-car-'.str()->random(6),
        'year' => 2022,
        'km' => 45000,
        'price' => 300000,
        'city' => 'Monterrey, NL',
        'description' => 'Auto de prueba para pagos.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

function paymentSubscription(User $user, Car $car, Plan $plan): Subscription
{
    return Subscription::query()->create([
        'user_id' => $user->getKey(),
        'plan_id' => $plan->getKey(),
        'car_id' => $car->getKey(),
        'status' => 'pending',
        'billing_cycle' => 'total',
        'amount' => 1000,
    ]);
}

it('generates spei instructions and a pending payment', function () {
    $this->seed();
    SiteSetting::put('payments', ['spei_enabled' => true]);

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    $this->actingAs($user)
        ->post("/checkout/{$subscription->getKey()}/pay", ['method' => 'spei'])
        ->assertRedirect("/checkout/{$subscription->getKey()}");

    $payment = Payment::query()->where('subscription_id', $subscription->getKey())->first();

    expect($payment)->not->toBeNull();
    expect($payment->provider)->toBe('spei');
    expect($payment->status)->toBe('pending');
    expect($payment->provider_reference)->toStartWith('WECAR-');
});

it('rejects payment methods disabled in settings', function () {
    $this->seed();
    SiteSetting::put('payments', ['spei_enabled' => false]);

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    $this->actingAs($user)
        ->post("/checkout/{$subscription->getKey()}/pay", ['method' => 'spei'])
        ->assertStatus(422);
});

it('activates the subscription and car when a payment is marked paid', function () {
    $this->seed();
    Mail::fake();

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);
    $payment = Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $subscription->getKey(),
        'provider' => 'spei',
        'amount' => 1000,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    app(PaymentService::class)->markPaid($payment);

    expect($payment->fresh()->status)->toBe('paid');
    expect($subscription->fresh()->status)->toBe('active');
    expect($car->fresh()->status?->name)->toBe('en_venta');
});

it('publishes paid-plan cars only after checkout', function () {
    $this->seed();
    $user = paymentUser();
    $plan = paymentPlan();
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    CarStatus::query()->firstOrCreate(['name' => 'pendiente'], ['label' => 'Pendiente']);
    $version = CarVersion::query()->create([
        'model_id' => $model->getKey(),
        'name' => 'LE',
        'year' => '2023',
    ]);

    $response = $this->actingAs($user)->post('/vende', [
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'version_id' => $version->getKey(),
        'year' => 2023,
        'km' => 12000,
        'price' => 350000,
        'description' => 'Auto en perfectas condiciones con servicio al día.',
        'city' => 'Monterrey, NL',
        'plan_key' => $plan->key_name,
        'images' => [UploadedFile::fake()->image('carro.jpg')],
    ]);

    $subscription = Subscription::query()->where('user_id', $user->getKey())->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('pending');

    $response->assertRedirect('/checkout/'.$subscription->getKey());

    $this->get('/comprar')
        ->assertInertia(fn (Assert $page) => $page->where('cars.meta.total', 0));
});

it('falls back to text search when openai is not configured', function () {
    $this->seed();
    config()->set('services.openai.key', null);

    $this->postJson('/api/ai-search', ['q' => 'camioneta automática'])
        ->assertOk()
        ->assertJson([
            'source' => 'fallback',
            'filters' => ['q' => 'camioneta automática'],
        ]);
});

it('processes didit webhooks with a valid signature', function () {
    $this->seed();
    config()->set('services.didit.shared_secret', 'didit-secret');

    $user = paymentUser();
    Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => $user->name,
        'didit_session_id' => 'session-123',
        'didit_status' => 'pending',
    ]);

    $payload = json_encode(['session_id' => 'session-123', 'status' => 'Approved']);
    $signature = hash_hmac('sha256', $payload, 'didit-secret');

    $this->call('POST', '/webhooks/didit', [], [], [], [
        'HTTP_X-SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect(Profile::query()->where('didit_session_id', 'session-123')->first()->didit_status)->toBe('approved');
});

it('renders checkout only for the subscription owner', function () {
    $this->seed();

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Checkout'));

    $other = paymentUser();

    $this->actingAs($other)->get("/checkout/{$subscription->getKey()}")->assertForbidden();
});

it('returns external search results when firecrawl is configured', function () {
    $this->seed();
    config()->set('services.firecrawl.key', 'test-key');

    Http::fake([
        'api.firecrawl.dev/*' => Http::response([
            'data' => [
                [
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
                ],
            ],
        ]),
    ]);

    $this->postJson('/api/external-search', ['q' => 'toyota corolla'])
        ->assertOk()
        ->assertJsonPath('results.0.title', 'Toyota Corolla 2020');
});

it('returns 422 for external search without firecrawl key', function () {
    config()->set('services.firecrawl.key', null);

    $this->postJson('/api/external-search', ['q' => 'toyota corolla'])->assertStatus(422);
});

it('stores push subscriptions for a user', function () {
    $this->seed();
    $user = paymentUser();

    $this->actingAs($user)
        ->postJson('/api/push/subscribe', [
            'endpoint' => 'https://push.example.com/endpoint',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
        ])
        ->assertOk();

    expect(PushSubscription::query()->where('user_id', $user->getKey())->count())->toBe(1);
});

it('allows admin to approve a pending payment', function () {
    $this->seed();
    Mail::fake();

    $admin = paymentUser();
    $admin->assignRole('superadmin');

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);
    $payment = Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $subscription->getKey(),
        'provider' => 'spei',
        'amount' => 1000,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/pagos/{$payment->getKey()}", ['status' => 'paid'])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('paid');
    expect($subscription->fresh()->status)->toBe('active');
});
