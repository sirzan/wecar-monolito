<?php

use App\Mail\PaymentReceipt;
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
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
    SiteSetting::put('payments', [
        'spei_enabled' => true,
        'spei_bank_name' => 'Banco de prueba',
        'spei_clabe' => '012345678901234567',
        'spei_beneficiary' => 'WeCar tech de México',
    ]);

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

it('uses an Inertia external location for Stripe checkout', function () {
    $this->seed();
    SiteSetting::put('payments', [
        'stripe_enabled' => true,
        'tokens_enabled' => true,
    ]);

    $user = paymentUser();
    $plan = Plan::query()->create([
        'key_name' => 'stripe_tokens_test',
        'type' => 'tokens',
        'name' => 'Tokens Stripe Test',
        'price' => 49,
        'token_amount' => 5,
        'is_active' => true,
    ]);
    $subscription = Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'tokens',
        'plan_id' => $plan->getKey(),
        'status' => 'pending',
        'billing_cycle' => 'one_time',
        'amount' => 49,
    ]);

    $stripe = Mockery::mock(StripeService::class);
    $stripe->shouldReceive('enabled')->andReturn(true);
    $stripe->shouldReceive('createCheckoutSession')->once()->andReturn([
        'id' => 'cs_test_123',
        'url' => 'https://checkout.stripe.com/cs_test_123',
    ]);
    $this->app->instance(StripeService::class, $stripe);

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post("/checkout/{$subscription->getKey()}/pay", ['method' => 'stripe'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/cs_test_123');
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
        'HTTP_X_SIGNATURE' => $signature,
        'HTTP_X_TIMESTAMP' => (string) time(),
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

it('does not allow an already paid payment to be changed manually', function () {
    $this->seed();

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
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch("/admin/pagos/{$payment->getKey()}", ['status' => 'refunded'])
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect($payment->fresh()->status)->toBe('paid');
});

/**
 * @return array<string, mixed>
 */
function vendePayload(Plan $plan, array $overrides = []): array
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    CarStatus::query()->firstOrCreate(['name' => 'pendiente'], ['label' => 'Pendiente']);
    $version = CarVersion::query()->firstOrCreate(
        ['model_id' => $model->getKey(), 'name' => 'LE', 'year' => '2023'],
    );

    return array_merge([
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
    ], $overrides);
}

it('hides unconfigured or disabled payment methods in Inertia shared props, checkout and panel', function () {
    $this->seed();
    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');
    config()->set('services.stripe.secret', null);
    config()->set('services.paypal.client_id', null);
    config()->set('services.paypal.client_secret', null);

    SiteSetting::put('payments', [
        'mercadopago_enabled' => true,
        'stripe_enabled' => true,
        'paypal_enabled' => false,
        'spei_enabled' => true,
        'spei_bank_name' => '',
        'spei_clabe' => '',
        'spei_beneficiary' => '',
    ]);

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    $this->actingAs($user)
        ->get('/vende')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('paymentMethods.mercadopago', true)
            ->where('paymentMethods.stripe', false)
            ->where('paymentMethods.paypal', false)
            ->where('paymentMethods.spei', false)
            ->has('paymentMethodOptions', 1)
            ->where('paymentMethodOptions.0.key', 'mercadopago'));

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('methods.mercadopago', true)
            ->where('methods.stripe', false)
            ->where('methods.paypal', false)
            ->where('methods.spei', false)
            ->has('methodOptions', 1)
            ->where('methodOptions.0.key', 'mercadopago'));

    $this->actingAs($user)
        ->get('/panel/metodos-pago')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('methods.mercadopago', true)
            ->where('methods.stripe', false)
            ->has('methodOptions', 1)
            ->where('methodOptions.0.key', 'mercadopago'));
});

it('initiates Mercado Pago Checkout Pro preference directly from vende submission', function () {
    $this->seed();
    Storage::fake('public');

    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');
    config()->set('services.mercadopago.sandbox', false);

    SiteSetting::put('payments', [
        'mercadopago_enabled' => true,
    ]);

    Http::fake([
        'https://api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_123456',
            'init_point' => 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=pref_123456',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com.mx/checkout/v1/redirect?pref_id=pref_123456',
        ], 201),
    ]);

    $user = paymentUser();
    $plan = paymentPlan();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('/vende', vendePayload($plan, ['payment_method' => 'mercadopago']))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=pref_123456');

    $subscription = Subscription::query()->where('user_id', $user->getKey())->firstOrFail();
    $payment = Payment::query()->where('subscription_id', $subscription->getKey())->firstOrFail();

    expect($subscription->payment_method)->toBe('mercadopago');
    expect($payment->provider)->toBe('mercadopago');
    expect($payment->provider_reference)->toBe('pref_123456');
    expect($payment->status)->toBe('pending');

    Http::assertSent(function ($request) use ($subscription): bool {
        return $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request->hasHeader('Authorization', 'Bearer APP_USR-token')
            && data_get($request->data(), 'external_reference') === $subscription->getKey()
            && data_get($request->data(), 'items.0.currency_id') === 'MXN'
            && (float) data_get($request->data(), 'items.0.unit_price') === 1000.0;
    });
});

it('initiates SPEI instructions directly from vende submission when selected', function () {
    $this->seed();
    Storage::fake('public');

    SiteSetting::put('payments', [
        'spei_enabled' => true,
        'spei_bank_name' => 'BBVA México',
        'spei_clabe' => '012345678901234567',
        'spei_beneficiary' => 'WeCar tech de México',
    ]);

    $user = paymentUser();
    $plan = paymentPlan();

    $response = $this->actingAs($user)
        ->post('/vende', vendePayload($plan, ['payment_method' => 'spei']));

    $subscription = Subscription::query()->where('user_id', $user->getKey())->firstOrFail();
    $payment = Payment::query()->where('subscription_id', $subscription->getKey())->firstOrFail();

    $response->assertRedirect("/checkout/{$subscription->getKey()}");
    expect($subscription->payment_method)->toBe('spei');
    expect($payment->provider)->toBe('spei');
    expect($payment->provider_reference)->toStartWith('WECAR-');
});

it('rejects disabled or unconfigured payment_method on vende without leaving an orphan car', function () {
    $this->seed();
    Storage::fake('public');
    config()->set('services.stripe.secret', null);

    SiteSetting::put('payments', [
        'stripe_enabled' => true,
        'mercadopago_enabled' => false,
    ]);

    $user = paymentUser();
    $plan = paymentPlan();

    $this->actingAs($user)
        ->postJson('/vende', vendePayload($plan, ['payment_method' => 'stripe']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method');

    expect(Car::query()->count())->toBe(0);
    expect(Subscription::query()->count())->toBe(0);
});

it('verifies Mercado Pago return via SDK and activates approved publication payment', function () {
    $this->seed();
    Mail::fake();

    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $subscription->getKey(),
        'plan_id' => $plan->getKey(),
        'provider' => 'mercadopago',
        'provider_reference' => 'pref_abc',
        'amount' => 1000,
        'currency' => 'MXN',
        'status' => 'pending',
        'metadata' => ['preference_id' => 'pref_abc'],
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/987654321' => Http::response([
            'id' => 987654321,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => $subscription->getKey(),
            'currency_id' => 'MXN',
            'transaction_amount' => 1000.0,
            'payment_method_id' => 'visa',
            'payment_type_id' => 'credit_card',
        ], 200),
    ]);

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}/mercadopago/return?payment_id=987654321&status=approved&preference_id=pref_abc")
        ->assertRedirect(route('autos.show', $car->slug));

    $payment = Payment::query()->where('subscription_id', $subscription->getKey())->firstOrFail();

    expect($payment->status)->toBe('paid');
    expect($payment->provider_reference)->toBe('987654321');
    expect($subscription->fresh()->status)->toBe('active');
    expect($car->fresh()->status?->name)->toBe('en_venta');
    Mail::assertQueued(PaymentReceipt::class);
});

it('keeps publication pending when Mercado Pago payment awaits accreditation', function () {
    $this->seed();

    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');
    SiteSetting::put('payments', ['mercadopago_enabled' => true]);

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/555666777' => Http::response([
            'id' => 555666777,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_payment',
            'external_reference' => $subscription->getKey(),
            'currency_id' => 'MXN',
            'transaction_amount' => 1000.0,
            'payment_method_id' => 'oxxo',
            'payment_type_id' => 'ticket',
        ], 200),
    ]);

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}/mercadopago/return?payment_id=555666777&status=pending")
        ->assertRedirect("/checkout/{$subscription->getKey()}");

    expect($subscription->fresh()->status)->toBe('pending');
    expect($car->fresh()->status?->name)->toBe('pendiente');

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mercadopagoPending.payment_id', '555666777')
            ->where('mercadopagoPending.payment_method', 'oxxo'));
});

it('rejects mismatched Mercado Pago payment details on return', function () {
    $this->seed();

    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/111222333' => Http::response([
            'id' => 111222333,
            'status' => 'approved',
            'external_reference' => $subscription->getKey(),
            'currency_id' => 'MXN',
            'transaction_amount' => 10.0,
        ], 200),
    ]);

    $this->actingAs($user)
        ->get("/checkout/{$subscription->getKey()}/mercadopago/return?payment_id=111222333&status=approved")
        ->assertStatus(422);

    expect($subscription->fresh()->status)->toBe('pending');
    expect($car->fresh()->status?->name)->toBe('pendiente');
});

it('validates Mercado Pago webhook HMAC signature with SDK and confirms pending payment', function () {
    $this->seed();
    Mail::fake();

    $secret = 'mp-webhook-secret';
    config()->set('services.mercadopago.public_key', 'APP_USR-public');
    config()->set('services.mercadopago.access_token', 'APP_USR-token');
    config()->set('services.mercadopago.webhook_secret', $secret);

    $user = paymentUser();
    $plan = paymentPlan();
    $car = paymentCar($user);
    $subscription = paymentSubscription($user, $car, $plan);

    Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $subscription->getKey(),
        'plan_id' => $plan->getKey(),
        'provider' => 'mercadopago',
        'provider_reference' => '777888999',
        'amount' => 1000,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    $this->postJson('/webhooks/mercadopago?data.id=777888999&type=payment', [
        'type' => 'payment',
        'data' => ['id' => '777888999'],
    ], [
        'x-signature' => 'ts=1700000000,v1=invalidhash',
        'x-request-id' => 'req-abc',
    ])->assertStatus(401);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/777888999' => Http::response([
            'id' => 777888999,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => $subscription->getKey(),
            'currency_id' => 'MXN',
            'transaction_amount' => 1000.0,
            'payment_method_id' => 'oxxo',
            'payment_type_id' => 'ticket',
        ], 200),
    ]);

    $manifest = 'id:777888999;request-id:req-abc;ts:1700000000;';
    $hash = hash_hmac('sha256', $manifest, $secret);

    $this->postJson('/webhooks/mercadopago?data.id=777888999&type=payment', [
        'action' => 'payment.updated',
        'type' => 'payment',
        'data' => ['id' => '777888999'],
    ], [
        'x-signature' => "ts=1700000000,v1={$hash}",
        'x-request-id' => 'req-abc',
    ])->assertOk()->assertJson(['received' => true]);

    expect($subscription->fresh()->status)->toBe('active');
    expect($car->fresh()->status?->name)->toBe('en_venta');
    Mail::assertQueued(PaymentReceipt::class);
});
