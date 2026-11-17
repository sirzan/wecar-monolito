<?php

use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the registration form on the spanish route', function () {
    $this->get('/registro')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('redirects authenticated users away from the registration form', function () {
    $this->seed();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/registro')
        ->assertRedirect('/');
});

it('registers a personal user with profile and seller role', function () {
    $this->seed();

    $this->post('/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'account_type' => 'personal',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => true,
    ])->assertRedirect('/panel');

    $user = User::query()->where('email', 'ana@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->profile)->not->toBeNull();
    expect($user->hasRole('seller'))->toBeTrue();
});

it('registers an agency account and provisions the agency', function () {
    $this->seed();

    $this->post('/register', [
        'name' => 'Carlos Ruiz',
        'email' => 'agencia@example.com',
        'account_type' => 'agency',
        'agency_name' => 'Agencia Demo',
        'agency_phone' => '8112345678',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => true,
    ])->assertRedirect('/panel');

    $user = User::query()->where('email', 'agencia@example.com')->first();
    $agency = Agency::query()->where('owner_id', $user?->getKey())->first();

    expect($user)->not->toBeNull();
    expect($user->profile?->is_agency)->toBeTrue();
    expect($user->hasRole('agency_owner'))->toBeTrue();
    expect($agency)->not->toBeNull();
    expect($agency->name)->toBe('Agencia Demo');
    expect($agency->slug)->toBe('agencia-demo');
});

it('requires accepting terms to register', function () {
    $this->seed();

    $this->post('/register', [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'account_type' => 'personal',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => false,
    ])->assertSessionHasErrors('terms');

    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();
});

it('requires agency data when registering as agency', function () {
    $this->seed();

    $this->post('/register', [
        'name' => 'Carlos Ruiz',
        'email' => 'agencia@example.com',
        'account_type' => 'agency',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => true,
    ])->assertSessionHasErrors(['agency_name', 'agency_phone']);
});

it('allows an unverified user to correct their email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['email' => 'wrong@example.com']);

    $this->actingAs($user)
        ->put('/email/verification-email', [
            'email' => 'correct@example.com',
            'current_password' => 'password',
        ])
        ->assertRedirect();

    expect($user->fresh()->email)->toBe('correct@example.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('allows an unverified user to log out from the verification state', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
