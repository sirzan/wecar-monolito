<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Car;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class PublicationPlanService
{
    public function activeForUser(User $user, ?string $excludeSubscriptionId = null): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('user_id', $user->getKey())
            ->where('kind', 'publication')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($excludeSubscriptionId, fn ($query) => $query->whereKeyNot($excludeSubscriptionId))
            ->orderByDesc('ends_at')
            ->first();
    }

    public function latestForUser(User $user): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('user_id', $user->getKey())
            ->where('kind', 'publication')
            ->whereIn('status', ['active', 'expired', 'completed'])
            ->latest('ends_at')
            ->latest('created_at')
            ->first();
    }

    public function planFor(Subscription $subscription): ?Plan
    {
        $subscription->loadMissing('plan');

        if ($subscription->plan) {
            return $subscription->plan;
        }

        $key = data_get($subscription->metadata, 'plan_key');

        return $key ? Plan::query()->where('key_name', $key)->first() : null;
    }

    public function maxCars(Subscription $subscription): int
    {
        $plan = $this->planFor($subscription);
        $snapshot = (int) data_get($subscription->metadata, 'max_cars', 0);

        return max($snapshot, (int) ($plan?->max_cars ?? 1), 1);
    }

    public function activeCarsCount(User $user): int
    {
        return Car::query()
            ->where('owner_id', $user->getKey())
            ->whereNull('agency_id')
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']))
            ->count();
    }

    public function activeAgencyCarsCount(Agency $agency): int
    {
        return Car::query()
            ->where('agency_id', $agency->getKey())
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Plan $plan): array
    {
        return [
            'plan_key' => $plan->key_name,
            'plan_name' => $plan->name,
            'price' => (float) $plan->price,
            'duration_weeks' => (int) $plan->duration_weeks,
            'max_cars' => (int) $plan->max_cars,
            'audience' => $plan->audience,
        ];
    }

    public function planForAgency(Agency $agency): ?Plan
    {
        $agency->loadMissing('planRelation');

        if ($agency->planRelation) {
            return $agency->planRelation;
        }

        $plan = Plan::query()->where('key_name', $agency->plan)->first();

        if ($plan) {
            return $plan;
        }

        return $this->defaultAgencyPlan();
    }

    public function defaultAgencyPlan(): ?Plan
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->whereIn('audience', ['agency', 'both'])
            ->orderBy('sort_order')
            ->first();
    }
}
