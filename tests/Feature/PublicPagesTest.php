<?php

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the public home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Home'));
});

it('renders the public home page with the three publication plans', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->has('plans', 3)
            ->where('plans.0.key_name', 'basico')
            ->where('plans.1.key_name', 'destacado')
            ->where('plans.2.key_name', 'premium')
        );
});

it('renders the login page for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects guests from the panel and admin areas', function () {
    $this->get('/panel')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');
});

it('allows a superadmin to access the admin panel', function () {
    $this->seed();

    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
});

it('blocks a regular user from the admin panel', function () {
    $this->seed();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows a verified user to access the seller panel', function () {
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Panel/Dashboard'));
});
