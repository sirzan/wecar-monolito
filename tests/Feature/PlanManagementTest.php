<?php

use App\Mail\PlanExpiring;
use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\CarVersion;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanReminder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PlanExpirationService;
use App\Services\PlanReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function planFor(array $overrides = []): Plan
{
    return Plan::query()->create(array_merge([
        'key_name' => 'plan_'.str()->random(8),
        'type' => 'publication',
        'audience' => 'user',
        'name' => 'Plan de prueba',
        'price' => 500,
        'duration' => '30 días',
        'duration_weeks' => 4,
        'max_cars' => 2,
        'is_active' => true,
        'sort_order' => 20,
    ], $overrides));
}

function planUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function planCar(User $owner, array $overrides = []): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => $overrides['status'] ?? 'en_venta'], ['label' => 'En venta']);

    return Car::query()->create(array_merge([
        'owner_id' => $owner->getKey(),
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $status->getKey(),
        'slug' => 'plan-car-'.str()->random(8),
        'year' => 2023,
        'km' => 10000,
        'price' => 300000,
        'city' => 'Monterrey, NL',
        'description' => 'Auto de prueba para planes.',
        'plan' => 'plan_test',
        'is_published' => true,
        'published_at' => now(),
    ], array_diff_key($overrides, ['status' => true])));
}

function publishPayload(Plan $plan): array
{
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

    return [
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
    ];
}

it('filters publication plans by audience', function () {
    $this->seed();

    $user = planUser();
    $this->actingAs($user)
        ->get('/vende')
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans', 3)
            ->where('plans', fn ($plans) => collect($plans)->pluck('key_name')->doesntContain('agencia'))
        );

    $owner = planUser();
    Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-audiencia',
        'name' => 'Agencia Audiencia',
        'plan' => 'agencia',
        'max_cars' => 30,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->get('/vende')
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans', fn ($plans) => collect($plans)->pluck('key_name')->all() === ['agencia'])
        );
});

it('rejects inactive plans at publication', function () {
    Storage::fake('public');

    $plan = planFor(['is_active' => false]);
    $user = planUser();

    $this->actingAs($user)
        ->post('/vende', publishPayload($plan))
        ->assertSessionHasErrors('plan_key');
});

it('enforces the account plan car quota', function () {
    Storage::fake('public');

    $plan = planFor(['max_cars' => 1]);
    $user = planUser();
    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now(),
        'ends_at' => now()->addWeeks(4),
    ]);
    planCar($user, ['plan' => $plan->key_name]);

    $this->actingAs($user)
        ->post('/vende', publishPayload($plan))
        ->assertSessionHasErrors('plan_key');
});

it('uses the admin agency plan for new agencies', function () {
    $this->seed();

    $plan = Plan::query()->where('key_name', 'agencia')->firstOrFail();
    $plan->update(['max_cars' => 7, 'duration_weeks' => 2]);

    $this->post('/register', [
        'name' => 'Agencia Plan',
        'email' => 'agencia-plan@example.com',
        'account_type' => 'agency',
        'agency_name' => 'Agencia Plan',
        'agency_phone' => '8112345678',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => true,
    ])->assertRedirect('/panel');

    $agency = Agency::query()->where('name', 'Agencia Plan')->firstOrFail();

    expect($agency->plan_id)->toBe($plan->getKey())
        ->and($agency->max_cars)->toBe(7)
        ->and($agency->plan_expires_at->diffInDays(now()->addWeeks(2)))->toBeLessThan(1);
});

it('blocks expired agencies from publishing', function () {
    Storage::fake('public');

    $plan = planFor(['audience' => 'agency']);
    $owner = planUser();
    Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-vencida',
        'name' => 'Agencia Vencida',
        'plan' => $plan->key_name,
        'plan_id' => $plan->getKey(),
        'plan_expires_at' => now()->subDay(),
        'max_cars' => 10,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post('/vende', publishPayload($plan))
        ->assertSessionHasErrors('plan_key');
});

it('extends an active plan from its current expiration when renewed', function () {
    Mail::fake();

    $plan = planFor();
    $user = planUser();
    $current = Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now(),
        'ends_at' => now()->addDays(10),
    ]);
    $renewal = Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'pending',
        'billing_cycle' => 'total',
        'amount' => 500,
    ]);
    $payment = Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $renewal->getKey(),
        'plan_id' => $plan->getKey(),
        'provider' => 'spei',
        'amount' => 500,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    app(PaymentService::class)->markPaid($payment);

    expect($renewal->fresh()->status)->toBe('completed')
        ->and($current->fresh()->ends_at->toDateString())->toBe(now()->addDays(10)->addWeeks(4)->toDateString());
});

it('sends one reminder per plan stage and supports membership', function () {
    Mail::fake();

    $user = planUser();
    $plan = planFor(['type' => 'membership', 'audience' => 'user']);
    $subscription = Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'membership',
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now(),
        'ends_at' => now()->addDays(6),
    ]);

    $service = app(PlanReminderService::class);
    $first = $service->run();
    $second = $service->run();

    expect($first['reminders'])->toBe(1)
        ->and($second['reminders'])->toBe(0)
        ->and(PlanReminder::query()->where('subject_id', $subscription->getKey())->first()?->reminder_key)->toBe('7d');

    Mail::assertQueued(PlanExpiring::class, 1);

    expect($service->reminderKey(now()->addDays(3)))->toBe('3d')
        ->and($service->reminderKey(now()->addDay()))->toBe('1d')
        ->and($service->reminderKey(now()))->toBe('0d');
});

it('expires plans and pauses cars and agencies', function () {
    $plan = planFor();
    $user = planUser();
    $car = planCar($user, ['plan' => $plan->key_name]);
    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ]);

    $agencyPlan = planFor(['audience' => 'agency']);
    $owner = planUser();
    $agency = Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-expirada',
        'name' => 'Agencia Expirada',
        'plan' => $agencyPlan->key_name,
        'plan_id' => $agencyPlan->getKey(),
        'plan_expires_at' => now()->subDay(),
        'max_cars' => 5,
        'is_active' => true,
    ]);
    $agencyCar = planCar($owner, ['agency_id' => $agency->getKey(), 'plan' => $agencyPlan->key_name]);

    $result = app(PlanExpirationService::class)->expire();

    expect($result['cars'])->toBeGreaterThanOrEqual(2)
        ->and($car->fresh()->status?->name)->toBe('pausado')
        ->and($car->fresh()->is_published)->toBeFalse()
        ->and($agency->fresh()->is_active)->toBeFalse()
        ->and($agencyCar->fresh()->status?->name)->toBe('pausado');
});

it('exposes usage and renewal data in billing', function () {
    $plan = planFor(['max_cars' => 3]);
    $user = planUser();
    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now(),
        'ends_at' => now()->addWeeks(4),
    ]);
    planCar($user, ['plan' => $plan->key_name]);

    $this->actingAs($user)
        ->get('/panel/facturacion')
        ->assertInertia(fn (Assert $page) => $page
            ->where('activePlan.max_cars', 3)
            ->where('activePlan.used_cars', 1)
            ->has('activePlan.subscription_id')
        );
});

it('renews an expired account plan and republishes paused cars', function () {
    Mail::fake();

    $plan = planFor(['max_cars' => 1]);
    $user = planUser();
    $car = planCar($user, ['plan' => $plan->key_name, 'status' => 'pausado', 'is_published' => false]);
    Subscription::query()->create([
        'user_id' => $user->getKey(),
        'kind' => 'publication',
        'plan_id' => $plan->getKey(),
        'status' => 'expired',
        'billing_cycle' => 'total',
        'amount' => 500,
        'starts_at' => now()->subWeeks(5),
        'ends_at' => now()->subWeek(),
    ]);

    $this->actingAs($user)
        ->post('/panel/facturacion/renovar')
        ->assertRedirect();

    $renewal = Subscription::query()->where('user_id', $user->getKey())->where('status', 'pending')->firstOrFail();
    $payment = Payment::query()->create([
        'user_id' => $user->getKey(),
        'subscription_id' => $renewal->getKey(),
        'plan_id' => $plan->getKey(),
        'provider' => 'spei',
        'amount' => 500,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    app(PaymentService::class)->markPaid($payment);

    expect($renewal->fresh()->status)->toBe('active')
        ->and($car->fresh()->status?->name)->toBe('en_venta')
        ->and($car->fresh()->is_published)->toBeTrue();
});

it('renews an agency plan and republishes its paused cars', function () {
    Mail::fake();

    $plan = planFor(['audience' => 'agency', 'max_cars' => 2]);
    $owner = planUser();
    $agency = Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => 'agencia-renovacion',
        'name' => 'Agencia Renovación',
        'plan' => $plan->key_name,
        'plan_id' => $plan->getKey(),
        'plan_expires_at' => now()->subDay(),
        'max_cars' => 2,
        'is_active' => false,
    ]);
    $car = planCar($owner, [
        'agency_id' => $agency->getKey(),
        'plan' => $plan->key_name,
        'status' => 'pausado',
        'is_published' => false,
    ]);

    $subscription = Subscription::query()->create([
        'user_id' => $owner->getKey(),
        'kind' => 'agency',
        'agency_id' => $agency->getKey(),
        'plan_id' => $plan->getKey(),
        'status' => 'pending',
        'billing_cycle' => 'total',
        'amount' => 500,
    ]);
    $payment = Payment::query()->create([
        'user_id' => $owner->getKey(),
        'subscription_id' => $subscription->getKey(),
        'plan_id' => $plan->getKey(),
        'provider' => 'spei',
        'amount' => 500,
        'currency' => 'MXN',
        'status' => 'pending',
    ]);

    app(PaymentService::class)->markPaid($payment);

    expect($subscription->fresh()->status)->toBe('active')
        ->and($agency->fresh()->is_active)->toBeTrue()
        ->and($agency->fresh()->plan_expires_at->isFuture())->toBeTrue()
        ->and($car->fresh()->status?->name)->toBe('en_venta')
        ->and($car->fresh()->is_published)->toBeTrue();
});
