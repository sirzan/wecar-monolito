<?php

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
