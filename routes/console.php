<?php

use App\Services\ExternalSearch\ExternalSearchCacheService;
use App\Services\PlanExpirationService;
use App\Services\PlanReminderService;
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

Schedule::call(fn () => app(ExternalSearchCacheService::class)->prune())->daily();
Schedule::command('plans:send-reminders')->dailyAt('09:00')->timezone('America/Monterrey');
Schedule::command('plans:process-expirations')->hourlyAt(5)->timezone('America/Monterrey');
