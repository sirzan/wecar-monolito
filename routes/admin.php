<?php

use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CouponRecipientController;
use App\Http\Controllers\Admin\CreditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancieraController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\VendeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:superadmin|manager|viewer', 'admin.write'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/autos', [CarController::class, 'index'])->name('cars');
    Route::get('/autos/crear', [CarController::class, 'create'])->middleware('role:superadmin|manager')->name('cars.create');
    Route::post('/autos', [VendeController::class, 'store'])->name('cars.store');
    Route::patch('/autos/{car:id}', [CarController::class, 'update'])->name('cars.update');
    Route::delete('/autos/{car:id}', [CarController::class, 'destroy'])->name('cars.destroy');

    Route::get('/usuarios', [UserController::class, 'index'])->name('users');
    Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/agencias', [AgencyController::class, 'index'])->name('agencies');
    Route::patch('/agencias/{agency:id}', [AgencyController::class, 'update'])->name('agencies.update');

    Route::get('/catalogos/{resource}', [CatalogController::class, 'index'])
        ->whereIn('resource', ['brands', 'models', 'versions', 'statuses'])
        ->name('catalogs');
    Route::post('/catalogos/{resource}', [CatalogController::class, 'store'])
        ->whereIn('resource', ['brands', 'models', 'versions', 'statuses'])
        ->name('catalogs.store');
    Route::patch('/catalogos/{resource}/{id}', [CatalogController::class, 'update'])
        ->whereIn('resource', ['brands', 'models', 'versions', 'statuses'])
        ->name('catalogs.update');
    Route::delete('/catalogos/{resource}/{id}', [CatalogController::class, 'destroy'])
        ->whereIn('resource', ['brands', 'models', 'versions', 'statuses'])
        ->name('catalogs.destroy');

    Route::get('/cupones', [CouponRecipientController::class, 'index'])->name('coupons');
    Route::post('/cupones', [CouponRecipientController::class, 'store'])->name('coupons.store');
    Route::delete('/cupones/{couponRecipient}', [CouponRecipientController::class, 'destroy'])->name('coupons.destroy');

    Route::get('/planes', [PlanController::class, 'index'])->name('plans');
    Route::post('/planes', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/planes/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/planes/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

    Route::get('/financieras', [FinancieraController::class, 'index'])->name('financieras');
    Route::post('/financieras', [FinancieraController::class, 'store'])->name('financieras.store');
    Route::patch('/financieras/{financiera}', [FinancieraController::class, 'update'])->name('financieras.update');
    Route::delete('/financieras/{financiera}', [FinancieraController::class, 'destroy'])->name('financieras.destroy');

    Route::get('/publicidad', [AdvertisementController::class, 'index'])->name('advertisements');
    Route::post('/publicidad', [AdvertisementController::class, 'store'])->name('advertisements.store');
    Route::patch('/publicidad/{advertisement}', [AdvertisementController::class, 'update'])->name('advertisements.update');
    Route::delete('/publicidad/{advertisement}', [AdvertisementController::class, 'destroy'])->name('advertisements.destroy');

    Route::get('/configuracion', [SettingController::class, 'index'])->name('settings');
    Route::patch('/configuracion', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/creditos', [CreditController::class, 'index'])->name('credits');
    Route::get('/creditos/{application}', [CreditController::class, 'show'])->name('credits.show');
    Route::patch('/creditos/{application}', [CreditController::class, 'update'])->name('credits.update');
    Route::get('/creditos/{application}/documentos/{slot}', [CreditController::class, 'document'])->name('credits.document');

    Route::get('/pagos', [PaymentController::class, 'index'])->name('payments');
    Route::patch('/pagos/{payment}', [PaymentController::class, 'update'])->name('payments.update');

    Route::get('/reportes', [ReportController::class, 'index'])->name('reports');
    Route::patch('/reportes/{report}', [ReportController::class, 'update'])->name('reports.update');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
});
