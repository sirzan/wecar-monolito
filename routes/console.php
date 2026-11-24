<?php

use App\Services\DiditService;
use App\Services\ExternalSearch\ExternalSearchCacheService;
use App\Services\PlanExpirationService;
use App\Services\PlanReminderService;
use App\Services\SitemapService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('plans:process-expirations', function () {
    $result = app(PlanExpirationService::class)->expire();

    $this->info(sprintf(
        'Planes vencidos: %d suscripciones, %d agencias, %d autos pausados.',
        $result['subscriptions'],
        $result['agencies'],
        $result['cars'],
    ));
})->purpose('Expira planes, pausa autos y bloquea agencias vencidas');

Artisan::command('plans:send-reminders', function () {
    $result = app(PlanReminderService::class)->run();

    $this->info(sprintf(
        'Recordatorios enviados: %d (%d suscripciones, %d agencias).',
        $result['reminders'],
        $result['subscriptions'],
        $result['agencies'],
    ));
})->purpose('Envía recordatorios de vencimiento de planes y membresías');

Artisan::command('seo:generate-sitemap', function () {
    $result = app(SitemapService::class)->generate();

    $this->info(sprintf(
        'Sitemap generado: %d páginas, %d autos, %d archivos.',
        $result['pages'],
        $result['cars'],
        $result['files'],
    ));
})->purpose('Genera el sitemap XML del portal y de los autos publicados');

Artisan::command('didit:sync-pending', function () {
    $result = app(DiditService::class)->syncPending();

    $this->info(sprintf(
        'Didit: %d sesiones revisadas, %d actualizadas, %d expiradas.',
        $result['checked'],
        $result['updated'],
        $result['expired'],
    ));
})->purpose('Reconcilia verificaciones de identidad pendientes o en revisión con Didit');

Artisan::command('external:repair-listings', function () {
    $result = app(ExternalSearchCacheService::class)->repairListings();

    $this->info(sprintf(
        'Listings reparados: %d. Cachés saneadas: %d.',
        $result['listings'],
        $result['caches'],
    ));
})->purpose('Limpia títulos y slugs corruptos de autos externos y su caché');

Schedule::call(fn () => app(ExternalSearchCacheService::class)->prune())
    ->daily()
    ->name('external-search-cache-prune')
    ->onOneServer()
    ->withoutOverlapping(30);
Schedule::command('seo:generate-sitemap')
    ->hourlyAt(15)
    ->timezone('America/Monterrey')
    ->onOneServer()
    ->withoutOverlapping(30);
Schedule::command('plans:send-reminders')
    ->dailyAt('09:00')
    ->timezone('America/Monterrey')
    ->onOneServer()
    ->withoutOverlapping(30);
Schedule::command('plans:process-expirations')
    ->hourlyAt(5)
    ->timezone('America/Monterrey')
    ->onOneServer()
    ->withoutOverlapping(30);
Schedule::command('didit:sync-pending')
    ->everyThirtyMinutes()
    ->timezone('America/Monterrey')
    ->onOneServer()
    ->withoutOverlapping(10);
