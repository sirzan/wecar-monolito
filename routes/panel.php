<?php

use App\Http\Controllers\Panel\AgencyController;
use App\Http\Controllers\Panel\BillingController;
use App\Http\Controllers\Panel\CreditController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\InvitationController;
use App\Http\Controllers\Panel\MembershipController;
use App\Http\Controllers\Panel\ProfileController;
use App\Http\Controllers\Panel\PublicationController;
use Illuminate\Support\Facades\Route;

Route::get('/invitaciones/{token}', [InvitationController::class, 'accept'])
    ->name('invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/publicaciones', [PublicationController::class, 'index'])->name('publications');
    Route::patch('/publicaciones/{car}/status', [PublicationController::class, 'updateStatus'])->name('publications.status');
    Route::delete('/publicaciones/{car}', [PublicationController::class, 'destroy'])->name('publications.destroy');

    Route::get('/tramites', [CreditController::class, 'index'])->name('credits');
    Route::get('/perfil-crediticio', [ProfileController::class, 'credit'])->name('credit-profile');
    Route::post('/perfil-crediticio', [ProfileController::class, 'updateCredit'])->name('credit-profile.update');

    Route::get('/configuracion', [ProfileController::class, 'edit'])->name('profile');
    Route::post('/configuracion', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar.update');
    Route::delete('/avatar', [ProfileController::class, 'destroyAvatar'])->name('avatar.destroy');

    Route::get('/facturacion', [BillingController::class, 'index'])->name('billing');

    Route::get('/membresia', [MembershipController::class, 'index'])->name('membership');
    Route::post('/membresia/checkout', [MembershipController::class, 'checkout'])->name('membership.checkout');
    Route::post('/membresia/tokens', [MembershipController::class, 'tokens'])->name('membership.tokens');

    Route::prefix('agencia')->name('agency.')->group(function () {
        Route::get('/', [AgencyController::class, 'current'])->name('current');
        Route::post('/switch', [AgencyController::class, 'switch'])->name('switch');
        Route::get('/crear', [AgencyController::class, 'create'])->name('create');
        Route::post('/crear', [AgencyController::class, 'store'])->name('store');

        Route::prefix('/{agency:slug}')->group(function () {
            Route::get('/resumen', [AgencyController::class, 'dashboard'])->name('dashboard');
            Route::get('/inventario', [AgencyController::class, 'inventory'])->name('inventory');
            Route::patch('/inventario/{car}', [AgencyController::class, 'updateCar'])->name('inventory.update');
            Route::delete('/inventario/{car}', [AgencyController::class, 'destroyCar'])->name('inventory.destroy');
            Route::get('/leads', [AgencyController::class, 'leads'])->name('leads');
            Route::patch('/leads/{lead}', [AgencyController::class, 'updateLead'])->name('leads.update');
            Route::get('/equipo', [AgencyController::class, 'team'])->name('team');
            Route::post('/equipo/invitar', [AgencyController::class, 'invite'])->name('team.invite');
            Route::patch('/equipo/{member}', [AgencyController::class, 'updateMember'])->name('team.update');
            Route::delete('/equipo/{member}', [AgencyController::class, 'removeMember'])->name('team.remove');
            Route::get('/perfil', [AgencyController::class, 'profile'])->name('profile');
            Route::post('/perfil', [AgencyController::class, 'updateProfile'])->name('profile.update');
            Route::get('/facturacion', [AgencyController::class, 'billing'])->name('billing');
            Route::get('/configuracion', [AgencyController::class, 'settings'])->name('settings');
        });
    });

    Route::get('/{section}', [DashboardController::class, 'section'])
        ->whereIn('section', ['favoritos', 'membresia', 'tramites', 'metodos-pago', 'soporte'])
        ->name('section');
});
