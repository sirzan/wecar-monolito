<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Car;
use App\Models\CarStatus;
use App\Models\Subscription;

class PlanExpirationService
{
    /**
     * @return array{subscriptions:int, agencies:int, cars:int}
     */
    public function expire(): array
    {
        $expiredSubscriptions = 0;
        $expiredAgencies = 0;
        $carsPaused = 0;

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with(['agency', 'car'])
            ->chunkById(500, function ($batch) use (&$expiredSubscriptions, &$carsPaused): void {
                foreach ($batch as $subscription) {
                    $updated = Subscription::query()
                        ->whereKey($subscription->getKey())
                        ->where('status', 'active')
                        ->update(['status' => 'expired']);

                    if ($updated === 0) {
                        continue;
                    }

                    $expiredSubscriptions++;
                    $subscription->status = 'expired';

                    if ($subscription->kind === 'publication') {
                        $carsPaused += $this->pausePublicationCars($subscription);
                    }

                    if (
                        $subscription->kind === 'agency'
                        && $subscription->agency
                        && ! $this->hasActiveReplacement($subscription)
                    ) {
                        $subscription->agency->update(['is_active' => false]);
                        $carsPaused += $this->pauseAgencyCars($subscription->agency);
                    }
                }
            });

        $agencies = Agency::query()
            ->where('is_active', true)
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now())
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('kind', 'agency')
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now())))
            ->chunkById(500, function ($batch) use (&$carsPaused, &$expiredAgencies): void {
                foreach ($batch as $agency) {
                    $agency->update(['is_active' => false]);
                    $expiredAgencies++;
                    $carsPaused += $this->pauseAgencyCars($agency);
                }
            });

        return [
            'subscriptions' => $expiredSubscriptions,
            'agencies' => $expiredAgencies,
            'cars' => $carsPaused,
        ];
    }

    private function pausePublicationCars(Subscription $subscription): int
    {
        $status = CarStatus::query()->firstOrCreate(['name' => 'pausado'], ['label' => 'Pausado']);

        if (! $subscription->user_id) {
            return (int) ($subscription->car?->update([
                'status_id' => $status->getKey(),
                'is_published' => false,
            ]) ?? 0);
        }

        if ($this->hasActiveReplacement($subscription)) {
            return 0;
        }

        $cars = Car::query()
            ->where('owner_id', $subscription->user_id)
            ->when($subscription->car_id, fn ($query) => $query->whereKey($subscription->car_id))
            ->when(! $subscription->car_id, fn ($query) => $query->whereNotNull('plan'))
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']));

        return $cars
            ->update([
                'status_id' => $status->getKey(),
                'is_published' => false,
            ]);
    }

    private function pauseAgencyCars(Agency $agency): int
    {
        $status = CarStatus::query()->firstOrCreate(['name' => 'pausado'], ['label' => 'Pausado']);

        return $agency->cars()
            ->where('is_published', true)
            ->update([
                'status_id' => $status?->getKey(),
                'is_published' => false,
            ]);
    }

    private function hasActiveReplacement(Subscription $subscription): bool
    {
        return Subscription::query()
            ->where('kind', $subscription->kind)
            ->where('status', 'active')
            ->whereKeyNot($subscription->getKey())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($subscription->agency_id, fn ($query) => $query->where('agency_id', $subscription->agency_id))
            ->when(! $subscription->agency_id, fn ($query) => $query->where('user_id', $subscription->user_id))
            ->exists();
    }
}
