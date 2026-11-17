<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('redirects to Google when the provider is configured', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    Socialite::fake('google');

    $this->get('/auth/google/redirect')->assertRedirect();
});

it('authenticates a user through Google', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'name' => 'Google User',
        'email' => 'google@example.com',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect('/panel');

    $user = User::query()->where('email', 'google@example.com')->firstOrFail();

    expect($user->name)->toBe('Google User')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('anything', $user->password))->toBeFalse();
});

it('redirects to login instead of throwing when Google is not configured', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);
    config()->set('services.google.redirect', null);

    $this->get('/auth/google/redirect')
        ->assertRedirect('/login')
        ->assertSessionHas('error', 'El inicio de sesión con Google no está configurado.');
});
