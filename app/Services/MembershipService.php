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

    public function begin(User $user): Subscription
    {
        $plan = $this->plan();

        abort_if(! $plan, 422, 'La membresía no está configurada.');

        return Subscription::query()->create([
            'user_id' => $user->getKey(),
            'kind' => 'membership',
            'plan_id' => $plan->getKey(),
            'car_id' => null,
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'amount' => (float) $plan->price,
            'metadata' => ['plan_key' => $plan->key_name, 'kind' => 'membership'],
        ]);
    }

    public function fulfill(Subscription $purchase): Subscription
    {
        $purchase->loadMissing('plan');

        $days = self::DURATION_DAYS;

        return DB::transaction(function () use ($purchase, $days): Subscription {
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
                $newEnd = $base->copy()->addDays($days);

                $existing->update(['ends_at' => $newEnd]);

                $purchase->update([
                    'status' => 'completed',
                    'starts_at' => $base,
                    'ends_at' => $newEnd,
                ]);

                return $existing->fresh();
            }

            $start = now();

            $purchase->update([
                'status' => 'active',
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($days),
            ]);

            return $purchase->fresh();
        });
    }
}
