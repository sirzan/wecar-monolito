<?php

namespace App\Services;

use App\Mail\PaymentReceipt;
use App\Models\CarStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function __construct(
        private readonly SearchTokenService $tokens,
        private readonly MembershipService $memberships,
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

        $this->activate($subscription, $payment->provider);
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
            $status = CarStatus::query()->where('name', 'en_venta')->first();

            $subscription->car->update([
                'status_id' => $status?->getKey() ?? $subscription->car->status_id,
                'is_published' => true,
                'published_at' => $subscription->car->published_at ?? now(),
            ]);
        }
    }
}
