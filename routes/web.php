<?php

use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CarPriceSuggestionController;
use App\Http\Controllers\CarVersionController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DiditController;
use App\Http\Controllers\ExternalSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VendeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/comprar', [PublicController::class, 'comprar'])->name('comprar');
Route::get('/comprar/marca/{brand:slug}', [PublicController::class, 'brandLanding'])->name('comprar.brand');
Route::get('/comprar/ciudad/{city}', [PublicController::class, 'cityLanding'])->name('comprar.city');
Route::get('/comprar/tipo/{body}', [PublicController::class, 'bodyLanding'])->name('comprar.body');
Route::get('/auto/externo/{id}', [PublicController::class, 'externalShow'])->name('external.show');
Route::get('/auto/{car}', [PublicController::class, 'autoShow'])->name('autos.show');
Route::get('/comparar', [PublicController::class, 'comparar'])->name('comparar');
Route::get('/vende', [PublicController::class, 'vende'])->name('vende');
Route::post('/vende', [VendeController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('vende.store');

Route::get('/registro', [RegisteredUserController::class, 'create'])
    ->middleware('guest:'.config('fortify.guard'))
    ->name('registro');
Route::get('/credito', [PublicController::class, 'credito'])->name('credito');
Route::get('/garantia', [PublicController::class, 'garantia'])->name('garantia');
Route::get('/certificaciones', [PublicController::class, 'certificaciones'])->name('certificaciones');
Route::get('/nosotros', [PublicController::class, 'nosotros'])->name('nosotros');
Route::get('/contacto', [PublicController::class, 'contacto'])->name('contacto');
Route::post('/contacto', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contacto.store');
Route::get('/terminos', [PublicController::class, 'terminos'])->name('terminos');
Route::get('/privacidad', [PublicController::class, 'privacidad'])->name('privacidad');
Route::get('/agencia/{slug}', [PublicController::class, 'agencia'])->name('agencia.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout/{subscription}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{subscription}/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::get('/checkout/{subscription}/stripe/success', [CheckoutController::class, 'stripeSuccess'])->name('checkout.stripe.success');
    Route::get('/checkout/{subscription}/paypal/return', [CheckoutController::class, 'paypalReturn'])->name('checkout.paypal.return');
    Route::post('/api/didit/session', [DiditController::class, 'createSession'])->middleware('throttle:10,1')->name('didit.session');
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/api/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/api/credito/documentos', [CreditController::class, 'documents'])->name('credit.documents');
    Route::post('/api/credito/documentos', [CreditController::class, 'uploadDocument'])->name('credit.documents.store');
    Route::post('/api/credito/solicitudes', [CreditController::class, 'submit'])->middleware('throttle:10,1')->name('credit.apply');
    Route::get('/api/credito/solicitudes/{application}/documentos/{slot}', [CreditController::class, 'showDocument'])->name('credit.documents.show');
    Route::post('/api/external-search/reward/start', [ExternalSearchController::class, 'rewardStart'])->middleware('throttle:5,1')->name('external-search.reward.start');
    Route::post('/api/external-search/reward/claim', [ExternalSearchController::class, 'rewardClaim'])->middleware('throttle:5,1')->name('external-search.reward.claim');
    Route::post('/api/push/subscribe', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::delete('/api/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
});

Route::post('/webhooks/stripe', [CheckoutController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/didit', [DiditController::class, 'webhook'])->name('webhooks.didit');
Route::post('/api/webhooks/didit', [DiditController::class, 'webhook'])->name('webhooks.didit.api');

Route::post('/api/ai-search', AiSearchController::class)->middleware('throttle:20,1')->name('ai-search');
Route::get('/api/credito/financieras', [CreditController::class, 'financieras'])->name('credit.financieras');
Route::post('/api/credito/evaluar', [CreditController::class, 'evaluate'])->middleware('throttle:30,1')->name('credit.evaluate');
Route::get('/api/external-search/status', [ExternalSearchController::class, 'status'])->middleware('throttle:60,1')->name('external-search.status');
Route::get('/api/external-search/cached', [ExternalSearchController::class, 'cached'])->middleware('throttle:60,1')->name('external-search.cached');
Route::post('/api/external-search/resolve', [ExternalSearchController::class, 'resolve'])->middleware('throttle:30,1')->name('external-search.resolve');
Route::post('/api/external-search', [ExternalSearchController::class, 'search'])->middleware('throttle:10,1')->name('external-search');
Route::get('/api/car-versions', CarVersionController::class)->middleware('throttle:30,1')->name('car-versions');
Route::get('/api/car-price-suggestions/comparables', [CarPriceSuggestionController::class, 'comparables'])->middleware('throttle:30,1')->name('price-suggestions.comparables');
Route::get('/api/car-price-suggestions/market', [CarPriceSuggestionController::class, 'market'])->middleware('throttle:20,1')->name('price-suggestions.market');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/{file}', [SitemapController::class, 'show'])
    ->where('file', '^(pages|cars-\d{1,5})\.xml$')
    ->name('sitemap.file');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
