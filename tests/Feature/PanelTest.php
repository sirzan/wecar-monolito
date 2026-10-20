<?php

use App\Mail\AgencyInvitation;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function panelUser(array $attributes = []): User
{
    return User::factory()->create(['email_verified_at' => now(), ...$attributes]);
}

function panelAgency(User $owner, string $name = 'Agencia Test'): Agency
{
    return Agency::query()->create([
        'owner_id' => $owner->getKey(),
        'slug' => str($name)->slug().'-'.str()->random(5),
        'name' => $name,
        'plan' => 'agencia',
        'max_cars' => 30,
        'is_active' => true,
    ]);
}

function panelCar(User $owner, ?Agency $agency, string $slug, int $price = 250000): Car
{
    $brand = CarBrand::query()->firstOrCreate(['slug' => 'toyota'], ['name' => 'Toyota']);
    $model = CarModel::query()->firstOrCreate(
        ['brand_id' => $brand->getKey(), 'slug' => 'corolla'],
        ['name' => 'Corolla'],
    );
    $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En Venta']);

    return Car::query()->create([
        'owner_id' => $owner->getKey(),
        'agency_id' => $agency?->getKey(),
        'seller_id' => $agency ? $owner->getKey() : null,
        'brand_id' => $brand->getKey(),
        'model_id' => $model->getKey(),
        'status_id' => $status->getKey(),
        'slug' => $slug,
        'year' => 2022,
        'km' => 45000,
        'price' => $price,
        'city' => 'Monterrey, NL',
        'description' => 'Auto de prueba para el panel.',
        'is_published' => true,
        'published_at' => now(),
    ]);
}

it('allows a verified seller into the panel', function () {
    $user = panelUser();

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Panel/Dashboard'));
});

it('only lists own and member-agency cars in publications', function () {
    $this->seed();
    $owner = panelUser();
    $agency = panelAgency($owner);
    $other = panelUser();

    $own = panelCar($owner, null, 'own-car');
    $agencyCar = panelCar($owner, $agency, 'agency-car');
    panelCar($other, null, 'other-car');

    $this->actingAs($owner)
        ->get('/panel/publicaciones')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Panel/Publications')
            ->has('cars.data', 2)
        );
});

it('shows the agency dashboard to its owner', function () {
    $this->seed();
    $owner = panelUser();
    $agency = panelAgency($owner);

    $this->actingAs($owner)
        ->get("/panel/agencia/{$agency->slug}/resumen")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Panel/Agency/Dashboard')
            ->where('agency.id', $agency->getKey())
        );
});

it('blocks non-members from an agency', function () {
    $this->seed();
    $owner = panelUser();
    $agency = panelAgency($owner);
    $outsider = panelUser();

    $this->actingAs($outsider)
        ->get("/panel/agencia/{$agency->slug}/resumen")
        ->assertForbidden();
});

it('allows an active agency seller to view the team but not manage it', function () {
    $this->seed();
    $owner = panelUser();
    $agency = panelAgency($owner);
    $seller = panelUser();

    AgencyMember::query()->create([
        'agency_id' => $agency->getKey(),
        'user_id' => $seller->getKey(),
        'invited_email' => $seller->email,
        'role' => 'seller',
        'status' => 'active',
        'accepted_at' => now(),
    ]);

    $this->actingAs($seller)
        ->get("/panel/agencia/{$agency->slug}/equipo")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Panel/Agency/Team')
            ->where('canManage', false)
        );

    $this->actingAs($seller)
        ->post("/panel/agencia/{$agency->slug}/equipo/invitar", [
            'email' => 'nuevo@example.com',
            'role' => 'seller',
        ])
        ->assertForbidden();
});

it('allows the owner to invite a member and queues an email', function () {
    $this->seed();
    Mail::fake();

    $owner = panelUser();
    $agency = panelAgency($owner);

    $this->actingAs($owner)
        ->post("/panel/agencia/{$agency->slug}/equipo/invitar", [
            'email' => 'vendedor@example.com',
            'role' => 'seller',
        ])
        ->assertRedirect();

    $member = AgencyMember::query()->where('invited_email', 'vendedor@example.com')->first();

    expect($member)->not->toBeNull();
    expect($member->token)->not->toBeNull();

    Mail::assertQueued(AgencyInvitation::class);
});

it('accepts an invitation for the matching email', function () {
    $this->seed();
    $owner = panelUser();
    $agency = panelAgency($owner);
    $invitee = panelUser(['email' => 'invitado@example.com']);

    $member = AgencyMember::query()->create([
        'agency_id' => $agency->getKey(),
        'invited_email' => $invitee->email,
        'invited_by' => $owner->getKey(),
        'role' => 'seller',
        'status' => 'invited',
        'token' => 'token-de-prueba',
    ]);

    $this->actingAs($invitee)
        ->get('/panel/invitaciones/token-de-prueba')
        ->assertRedirect("/panel/agencia/{$agency->slug}/resumen");

    $member->refresh();

    expect($member->user_id)->toBe($invitee->getKey());
    expect($member->status)->toBe('active');
});

it('creates an agency for a user without one', function () {
    $this->seed();
    $user = panelUser();

    $this->actingAs($user)
        ->post('/panel/agencia/crear', [
            'name' => 'Agencia Nueva',
            'city' => 'Monterrey',
            'state' => 'Nuevo León',
        ])
        ->assertRedirect();

    $agency = Agency::query()->where('owner_id', $user->getKey())->first();

    expect($agency)->not->toBeNull();
    expect($user->fresh()->hasRole('agency_owner'))->toBeTrue();
});
