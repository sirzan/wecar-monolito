<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentCheckoutService
{
    public function __construct(
        private readonly MercadoPagoService $mercadopago,
        private readonly StripeService $stripe,
        private readonly PayPalService $paypal,
        private readonly SpeiService $spei,
        private readonly PaymentService $payments,
        private readonly SearchCouponService $coupons,
        private readonly PaymentMethodsService $paymentMethods,
    ) {}

    public function start(
        Subscription $subscription,
        User $user,
        string $method,
        ?string $couponCode = null,
    ): SymfonyResponse|RedirectResponse {
        $subscription->loadMissing(['plan', 'user']);
        abort_unless($subscription->plan, 422, 'La suscripción no tiene un plan válido.');

        $kind = $subscription->kind === 'agency' ? 'agency' : ($subscription->plan?->type ?? $subscription->kind);

        if ($kind === 'tokens' && filled($couponCode)) {
            abort_unless(
                $this->coupons->validFor($user, $couponCode, $subscription->plan),
                422,
                'El cupón no es válido para este usuario.',
            );
        }

        if ($kind === 'tokens' && $subscription->plan) {
            $subscription->update([
                'amount' => $this->coupons->amount($subscription->plan, $user, $couponCode),
            ]);
        }

        $settings = SiteSetting::get('payments', []);
        $settings = is_array($settings) ? $settings : [];

        $kindEnabled = match ($kind) {
            'membership' => (bool) ($settings['membership_enabled'] ?? false),
            'tokens' => (bool) ($settings['tokens_enabled'] ?? false),
            default => true,
        };

        abort_unless($kindEnabled, 404, 'Este producto no está disponible.');
        abort_unless($this->paymentMethods->isAvailable($method, $settings), 422, 'Método de pago no disponible.');

        $subscription->update(['payment_method' => $method]);

        return match ($method) {
            'mercadopago' => $this->payWithMercadoPago($subscription),
            'stripe' => $this->payWithStripe($subscription),
            'paypal' => $this->payWithPayPal($subscription),
            'spei' => $this->payWithSpei($subscription),
            default => abort(422, 'Método de pago no válido.'),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function settleMercadoPagoPayment(
        Subscription $subscription,
        string $mpPaymentId,
        array $metadata = [],
        ?string $preferenceId = null,
    ): Payment {
        $payment = $this->resolveMercadoPagoPayment($subscription, $mpPaymentId, $metadata, $preferenceId);

        if ($payment->status !== 'paid') {
            $this->payments->markPaid($payment);
        }

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordMercadoPagoPending(
        Subscription $subscription,
        string $mpPaymentId,
        array $metadata = [],
        ?string $preferenceId = null,
    ): Payment {
        return $this->resolveMercadoPagoPayment($subscription, $mpPaymentId, $metadata, $preferenceId);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveMercadoPagoPayment(
        Subscription $subscription,
        string $mpPaymentId,
        array $metadata = [],
        ?string $preferenceId = null,
    ): Payment {
        $mergedMeta = array_merge($metadata, [
            'mp_payment_id' => $mpPaymentId,
        ]);

        $existing = Payment::query()
            ->where('provider', 'mercadopago')
            ->where('provider_reference', $mpPaymentId)
            ->first();

        if ($existing) {
            abort_unless($existing->subscription_id === $subscription->getKey(), 422);

            if ($existing->status === 'pending') {
                $existing->update([
                    'metadata' => array_merge($existing->metadata ?? [], $mergedMeta),
                ]);
            }

            return $existing->fresh();
        }

        $pendingPayment = $this->findPendingMercadoPagoPayment($subscription, $preferenceId);

        if ($pendingPayment) {
            try {
                $pendingPayment->update([
                    'provider_reference' => $mpPaymentId,
                    'metadata' => array_merge($pendingPayment->metadata ?? [], $mergedMeta),
                ]);

                return $pendingPayment->fresh();
            } catch (UniqueConstraintViolationException) {
                $concurrent = Payment::query()
                    ->where('provider', 'mercadopago')
                    ->where('provider_reference', $mpPaymentId)
                    ->firstOrFail();

                abort_unless($concurrent->subscription_id === $subscription->getKey(), 422);

                return $concurrent;
            }
        }

        return $this->payments->createPayment(
            $subscription,
            'mercadopago',
            (float) $subscription->amount,
            $mpPaymentId,
            $mergedMeta,
        );
    }

    private function findPendingMercadoPagoPayment(Subscription $subscription, ?string $preferenceId = null): ?Payment
    {
        $query = $subscription->payments()
            ->where('provider', 'mercadopago')
            ->where('status', 'pending');

        if (filled($preferenceId)) {
            $matched = (clone $query)
                ->where(function ($subQuery) use ($preferenceId): void {
                    $subQuery->where('provider_reference', $preferenceId)
                        ->orWhere('metadata->preference_id', $preferenceId);
                })
                ->latest()
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        return $query
            ->whereNull('metadata->mp_payment_id')
            ->latest()
            ->first();
    }

    private function payWithMercadoPago(Subscription $subscription): SymfonyResponse
    {
        $subscription->loadMissing(['plan', 'user']);

        $preference = $this->mercadopago->createPreference(
            $subscription,
            route('checkout.mercadopago.return', $subscription),
            route('checkout.mercadopago.return', $subscription),
            route('checkout.mercadopago.return', ['subscription' => $subscription, 'status' => 'failure']),
            route('webhooks.mercadopago'),
        );

        $this->payments->createPayment($subscription, 'mercadopago', (float) $subscription->amount, $preference['id'], [
            'preference_id' => $preference['id'],
        ]);

        return Inertia::location($preference['checkout_url']);
    }

    private function payWithStripe(Subscription $subscription): SymfonyResponse
    {
        $subscription->loadMissing(['plan', 'user']);

        $session = $this->stripe->createCheckoutSession(
            $subscription,
            route('checkout.stripe.success', $subscription).'?session_id={CHECKOUT_SESSION_ID}',
            route('checkout.show', $subscription),
        );

        $this->payments->createPayment($subscription, 'stripe', (float) $subscription->amount, $session['id'], [
            'checkout_session_id' => $session['id'],
        ]);

        return Inertia::location($session['url']);
    }

    private function payWithPayPal(Subscription $subscription): SymfonyResponse
    {
        $subscription->loadMissing(['plan']);

        $order = $this->paypal->createOrder(
            $subscription,
            route('checkout.paypal.return', $subscription),
            route('checkout.show', $subscription),
        );

        $this->payments->createPayment($subscription, 'paypal', (float) $subscription->amount, $order['id'], [
            'order_id' => $order['id'],
        ]);

        return Inertia::location($order['approval_url']);
    }

    private function payWithSpei(Subscription $subscription): RedirectResponse
    {
        $subscription->payments()
            ->where('provider', 'spei')
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        $instructions = $this->spei->instructions((float) $subscription->amount);

        $this->payments->createPayment($subscription, 'spei', (float) $subscription->amount, $instructions['reference'], [
            'bank_name' => $instructions['bank_name'],
            'clabe' => $instructions['clabe'],
            'beneficiary' => $instructions['beneficiary'],
        ]);

        return redirect()
            ->route('checkout.show', $subscription)
            ->with('success', 'Instrucciones SPEI generadas. Realiza tu transferencia para activar la publicación.');
    }
}
