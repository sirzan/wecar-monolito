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
        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        $carsPaused = 0;

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);

            if ($subscription->kind === 'publication') {
                $carsPaused += $this->pausePublicationCars($subscription);
            }

            if ($subscription->kind === 'agency' && $subscription->agency) {
                $subscription->agency->update(['is_active' => false]);
                $carsPaused += $this->pauseAgencyCars($subscription->agency);
            }
        }

        $agencies = Agency::query()
            ->where('is_active', true)
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now())
            ->get();

        foreach ($agencies as $agency) {
            $agency->update(['is_active' => false]);
            $carsPaused += $this->pauseAgencyCars($agency);
        }

        return [
            'subscriptions' => $subscriptions->count(),
            'agencies' => $agencies->count(),
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

        return Car::query()
            ->where('owner_id', $subscription->user_id)
            ->where(fn ($query) => $query
                ->whereNotNull('plan')
                ->when($subscription->car_id, fn ($query) => $query->orWhereKey($subscription->car_id)))
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']))
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
}
