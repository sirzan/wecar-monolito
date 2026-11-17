<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public const PLAN_KEY = 'membresia_ext_499';

    public const DURATION_DAYS = 30;

    public function plan(): ?Plan
    {
        return Plan::query()
            ->where('key_name', self::PLAN_KEY)
            ->where('is_active', true)
            ->first();
    }

    public function active(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->getKey())
            ->where('kind', 'membership')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('ends_at')
            ->first();
    }

    public function isSubscribed(?User $user): bool
    {
        return $user !== null && $this->active($user) !== null;
    }

    public function begin(User $user, ?Plan $selectedPlan = null): Subscription
    {
        $plan = $selectedPlan ?? $this->plan();

        abort_if(! $plan, 422, 'La membresía no está configurada.');
        abort_unless($plan->isMembership() && $plan->is_active, 422, 'El plan de membresía no está disponible.');

        return Subscription::query()->create([
            'user_id' => $user->getKey(),
            'kind' => 'membership',
            'plan_id' => $plan->getKey(),
            'car_id' => null,
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'amount' => $plan->price,
            'metadata' => [
                'plan_key' => $plan->key_name,
                'plan_name' => $plan->name,
                'duration_weeks' => (int) $plan->duration_weeks,
                'kind' => 'membership',
            ],
        ]);
    }

    public function fulfill(Subscription $purchase): Subscription
    {
        $purchase->loadMissing('plan');

        return DB::transaction(function () use ($purchase): Subscription {
            User::query()->whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();

            $purchase = Subscription::query()
                ->with('plan')
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($purchase->status, ['active', 'completed'], true)) {
                return $purchase->fresh();
            }

            $weeks = (int) ($purchase->plan?->duration_weeks ?? 0);

            $existing = Subscription::query()
                ->where('user_id', $purchase->user_id)
                ->where('kind', 'membership')
                ->where('status', 'active')
                ->whereKeyNot($purchase->getKey())
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest('ends_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $base = $existing->ends_at && $existing->ends_at->isFuture()
                    ? $existing->ends_at->copy()
                    : now();
                $newEnd = $weeks > 0
                    ? $base->copy()->addWeeks($weeks)
                    : $base->copy()->addDays(self::DURATION_DAYS);

                $existing->update(['ends_at' => $newEnd]);

                $purchase->update([
                    'status' => 'completed',
                    'starts_at' => $base,
                    'ends_at' => $newEnd,
                ]);

                return $existing->fresh();
            }

            $start = now();
            $end = $weeks > 0
                ? $start->copy()->addWeeks($weeks)
                : $start->copy()->addDays(self::DURATION_DAYS);

            $purchase->update([
                'status' => 'active',
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            return $purchase->fresh();
        });
    }
}
