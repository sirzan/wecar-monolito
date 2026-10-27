<?php

namespace App\Services;

use App\Mail\PaymentReceipt;
use App\Models\Agency;
use App\Models\Car;
use App\Models\CarStatus;
use App\Models\Payment;
use App\Models\PlanReminder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function __construct(
        private readonly SearchTokenService $tokens,
        private readonly MembershipService $memberships,
        private readonly PublicationPlanService $publicationPlans,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createPayment(
        Subscription $subscription,
        string $provider,
        float $amount,
        ?string $reference = null,
        array $metadata = [],
        string $status = 'pending',
    ): Payment {
        return Payment::query()->create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->getKey(),
            'plan_id' => $subscription->plan_id,
            'provider' => $provider,
            'provider_reference' => $reference,
            'amount' => $amount,
            'currency' => 'MXN',
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    public function markPaid(Payment $payment): void
    {
        $paid = DB::transaction(function () use ($payment): bool {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status === 'paid') {
                return false;
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $subscription = $locked->subscription;

            if ($subscription) {
                $this->fulfill($subscription, $locked);
            }

            return true;
        });

        if (! $paid) {
            return;
        }

        $payment->refresh()->load(['user', 'subscription.plan', 'subscription.car']);

        if ($payment->user) {
            Mail::to($payment->user->email)->queue(new PaymentReceipt($payment));
        }
    }

    public function fulfill(Subscription $subscription, Payment $payment): void
    {
        $subscription->loadMissing(['plan', 'car', 'user']);

        if ($subscription->plan?->isTokens()) {
            $amount = (int) ($subscription->plan->token_amount ?: 0);

            if ($amount > 0) {
                $this->tokens->credit(
                    $subscription->user,
                    $amount,
                    'purchase',
                    $payment->provider.':'.($payment->provider_reference ?? $payment->getKey()),
                );
            }

            $subscription->update([
                'status' => 'completed',
                'starts_at' => now(),
                'ends_at' => null,
            ]);

            return;
        }

        if ($subscription->plan?->isMembership() || $subscription->kind === 'membership') {
            $active = $this->memberships->fulfill($subscription);

            if ($active->getKey() !== $subscription->getKey()) {
                $payment->update(['subscription_id' => $active->getKey()]);
            }

            return;
        }

        if ($subscription->kind === 'agency') {
            $this->activateAgency($subscription, $payment->provider);

            return;
        }

        $current = $subscription->car_id === null
            ? $this->publicationPlans->activeForUser($subscription->user, $subscription->getKey())
            : null;

        if ($current) {
            $plan = $subscription->plan;
            $weeks = max((int) ($plan?->duration_weeks ?? 4), 1);
            $base = $current->ends_at && $current->ends_at->isFuture() ? $current->ends_at->copy() : now();
            $endsAt = $base->copy()->addWeeks($weeks);

            $current->update([
                'plan_id' => $plan?->getKey() ?? $current->plan_id,
                'ends_at' => $endsAt,
                'metadata' => $plan
                    ? array_merge($current->metadata ?? [], $this->publicationPlans->snapshot($plan))
                    : $current->metadata,
            ]);

            $subscription->update([
                'status' => 'completed',
                'payment_method' => $payment->provider,
                'starts_at' => $base,
                'ends_at' => $endsAt,
            ]);

            $this->clearSubscriptionReminders($current);

            return;
        }

        $this->activate($subscription, $payment->provider);
    }

    private function activateAgency(Subscription $subscription, ?string $paymentMethod = null): void
    {
        $agency = $subscription->agency;
        $plan = $subscription->plan;

        if (! $agency || ! $plan) {
            return;
        }

        $weeks = max((int) $plan->duration_weeks, 1);
        $base = $agency->plan_expires_at && $agency->plan_expires_at->isFuture()
            ? $agency->plan_expires_at->copy()
            : now();
        $endsAt = $base->copy()->addWeeks($weeks);

        $agency->update([
            'plan' => $plan->key_name,
            'plan_id' => $plan->getKey(),
            'plan_expires_at' => $endsAt,
            'max_cars' => max((int) $plan->max_cars, 1),
            'is_active' => true,
        ]);

        $this->reactivateAgencyCars($agency, max((int) $plan->max_cars, 1));
        $this->clearAgencyReminders($agency);
        $this->clearSubscriptionReminders($subscription);

        $subscription->update([
            'status' => 'active',
            'payment_method' => $paymentMethod ?? $subscription->payment_method,
            'starts_at' => $base,
            'ends_at' => $endsAt,
            'metadata' => array_merge($subscription->metadata ?? [], $this->publicationPlans->snapshot($plan)),
        ]);
    }

    public function activate(Subscription $subscription, ?string $paymentMethod = null): void
    {
        $subscription->loadMissing(['plan', 'car']);

        $startsAt = now();
        $endsAt = $subscription->plan?->duration_weeks
            ? $startsAt->copy()->addWeeks((int) $subscription->plan->duration_weeks)
            : $startsAt->copy()->addDays(30);

        $subscription->update([
            'status' => 'active',
            'payment_method' => $paymentMethod ?? $subscription->payment_method,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        if ($subscription->car) {
            $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En venta']);

            $subscription->car->update([
                'status_id' => $status->getKey(),
                'is_published' => true,
                'published_at' => $subscription->car->published_at ?? now(),
            ]);
        } elseif ($subscription->user) {
            $this->reactivateUserCars($subscription->user, max((int) ($subscription->plan?->max_cars ?? 1), 1));
        }

        $this->clearSubscriptionReminders($subscription);
    }

    private function clearSubscriptionReminders(Subscription $subscription): void
    {
        PlanReminder::query()
            ->where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->getKey())
            ->delete();
    }

    private function clearAgencyReminders(Agency $agency): void
    {
        PlanReminder::query()
            ->where('subject_type', Agency::class)
            ->where('subject_id', $agency->getKey())
            ->delete();
    }

    private function reactivateUserCars(User $user, int $limit): void
    {
        $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En venta']);
        $active = Car::query()
            ->where('owner_id', $user->getKey())
            ->whereNull('agency_id')
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']))
            ->count();
        $remaining = max($limit - $active, 0);

        if ($remaining === 0) {
            return;
        }

        Car::query()
            ->where('owner_id', $user->getKey())
            ->whereNull('agency_id')
            ->whereNotNull('plan')
            ->where('is_published', false)
            ->whereHas('status', fn ($query) => $query->where('name', 'pausado'))
            ->limit($remaining)
            ->update([
                'status_id' => $status->getKey(),
                'is_published' => true,
                'published_at' => now(),
            ]);
    }

    private function reactivateAgencyCars(Agency $agency, int $limit): void
    {
        $status = CarStatus::query()->firstOrCreate(['name' => 'en_venta'], ['label' => 'En venta']);
        $active = Car::query()
            ->where('agency_id', $agency->getKey())
            ->where('is_published', true)
            ->whereDoesntHave('status', fn ($query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']))
            ->count();
        $remaining = max($limit - $active, 0);

        if ($remaining === 0) {
            return;
        }

        Car::query()
            ->where('agency_id', $agency->getKey())
            ->where('is_published', false)
            ->whereHas('status', fn ($query) => $query->where('name', 'pausado'))
            ->limit($remaining)
            ->update([
                'status_id' => $status->getKey(),
                'is_published' => true,
                'published_at' => now(),
            ]);
    }
}
