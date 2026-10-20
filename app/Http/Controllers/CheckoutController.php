<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Services\PaymentService;
use App\Services\PayPalService;
use App\Services\SearchCouponService;
use App\Services\SpeiService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly PayPalService $paypal,
        private readonly SpeiService $spei,
        private readonly PaymentService $payments,
        private readonly SearchCouponService $coupons,
    ) {}

    public function show(Request $request, Subscription $subscription): Response|RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        $subscription->load(['plan', 'car.brand', 'car.model']);
        abort_unless($subscription->plan, 422, 'La suscripción no tiene un plan válido.');

        if (in_array($subscription->status, ['active', 'completed'], true)) {
            return $this->afterPayment($subscription, 'Tu pago ya está confirmado.');
        }

        $payments = SiteSetting::get('payments', []);

        $kind = $subscription->plan?->type ?? $subscription->kind;
        $enabledForKind = match ($kind) {
            'membership' => (bool) ($payments['membership_enabled'] ?? false),
            'tokens' => (bool) ($payments['tokens_enabled'] ?? false),
            default => true,
        };

        abort_unless($enabledForKind, 404, 'Este producto no está disponible.');
        $speiPayment = $subscription->payments()
            ->where('provider', 'spei')
            ->where('status', 'pending')
            ->latest()
            ->first();

        return Inertia::render('Public/Checkout', [
            'subscription' => [
                'id' => $subscription->getKey(),
                'status' => $subscription->status,
                'amount' => (float) $subscription->amount,
                'billing_cycle' => $subscription->billing_cycle,
                'kind' => $kind,
                'tokens' => (int) ($subscription->plan?->token_amount ?? 0),
            ],
            'plan' => $subscription->plan,
            'coupon' => [
                'code' => (string) config('external_search.token_coupon_code', 'TKNS50'),
                'available' => $kind === 'tokens' && $this->coupons->hasCoupon($request->user()),
                'amount' => $kind === 'tokens' ? $this->coupons->amount($subscription->plan, $request->user(), $this->coupons->hasCoupon($request->user()) ? (string) config('external_search.token_coupon_code', 'TKNS50') : null) : (float) $subscription->amount,
            ],
            'car' => $subscription->car ? [
                'id' => $subscription->car->getKey(),
                'slug' => $subscription->car->slug,
                'title' => trim(($subscription->car->brand?->name ?? '').' '.($subscription->car->model?->name ?? '').' '.$subscription->car->year),
                'price' => (float) $subscription->car->price,
                'image' => $subscription->car->image,
            ] : null,
            'methods' => [
                'stripe' => (bool) ($payments['stripe_enabled'] ?? false) && $this->stripe->enabled(),
                'paypal' => (bool) ($payments['paypal_enabled'] ?? false) && $this->paypal->enabled(),
                'spei' => (bool) ($payments['spei_enabled'] ?? false),
            ],
            'spei' => $speiPayment ? [
                'reference' => $speiPayment->provider_reference,
                'bank_name' => data_get($speiPayment->metadata, 'bank_name'),
                'clabe' => data_get($speiPayment->metadata, 'clabe'),
                'beneficiary' => data_get($speiPayment->metadata, 'beneficiary'),
                'amount' => (float) $speiPayment->amount,
            ] : null,
        ]);
    }

    public function pay(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        if (in_array($subscription->status, ['active', 'completed'], true)) {
            return $this->afterPayment($subscription, 'Tu compra ya está confirmada.');
        }

        $validated = $request->validate([
            'method' => ['required', Rule::in(['stripe', 'paypal', 'spei'])],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        $subscription->loadMissing(['plan', 'user']);
        abort_unless($subscription->plan, 422, 'La suscripción no tiene un plan válido.');
        $kind = $subscription->plan?->type ?? $subscription->kind;

        if ($kind === 'tokens' && filled($validated['coupon_code'] ?? null)) {
            abort_unless(
                $this->coupons->validFor($request->user(), $validated['coupon_code'], $subscription->plan),
                422,
                'El cupón no es válido para este usuario.',
            );
        }

        if ($kind === 'tokens' && $subscription->plan) {
            $subscription->update([
                'amount' => $this->coupons->amount(
                    $subscription->plan,
                    $request->user(),
                    $validated['coupon_code'] ?? null,
                ),
            ]);
        }

        $payments = SiteSetting::get('payments', []);
        $enabled = [
            'stripe' => (bool) ($payments['stripe_enabled'] ?? false) && $this->stripe->enabled(),
            'paypal' => (bool) ($payments['paypal_enabled'] ?? false) && $this->paypal->enabled(),
            'spei' => (bool) ($payments['spei_enabled'] ?? false),
        ];

        $kindEnabled = match ($kind) {
            'membership' => (bool) ($payments['membership_enabled'] ?? false),
            'tokens' => (bool) ($payments['tokens_enabled'] ?? false),
            default => true,
        };

        abort_unless($kindEnabled, 404, 'Este producto no está disponible.');

        abort_unless($enabled[$validated['method']], 422, 'Método de pago no disponible.');

        $subscription->update(['payment_method' => $validated['method']]);

        return match ($validated['method']) {
            'stripe' => $this->payWithStripe($subscription),
            'paypal' => $this->payWithPayPal($subscription),
            'spei' => $this->payWithSpei($subscription),
        };
    }

    public function stripeSuccess(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        $sessionId = (string) $request->query('session_id');
        abort_if($sessionId === '', 400);

        $session = $this->stripe->retrieveSession($sessionId);

        if ($session->payment_status === 'paid') {
            $payment = Payment::query()
                ->where('subscription_id', $subscription->getKey())
                ->where('provider', 'stripe')
                ->where('provider_reference', $sessionId)
                ->first()
                ?? $this->payments->createPayment($subscription, 'stripe', (float) $subscription->amount, $sessionId);

            $this->payments->markPaid($payment);
        }

        return $this->afterPayment($subscription->fresh(), 'Pago confirmado. Tu auto ya está publicado.');
    }

    public function paypalReturn(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        $orderId = (string) $request->query('token');
        abort_if($orderId === '', 400);

        $capture = $this->paypal->captureOrder($orderId);

        if (($capture['status'] ?? null) === 'COMPLETED') {
            $payment = Payment::query()
                ->where('subscription_id', $subscription->getKey())
                ->where('provider', 'paypal')
                ->where('provider_reference', $orderId)
                ->first()
                ?? $this->payments->createPayment($subscription, 'paypal', (float) $subscription->amount, $orderId);

            $this->payments->markPaid($payment);
        }

        return $this->afterPayment($subscription->fresh(), 'Pago confirmado. Tu auto ya está publicado.');
    }

    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = $this->stripe->constructEvent($payload, $signature);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if (($event->type ?? null) === 'checkout.session.completed') {
            $session = $event->data->object;
            $subscriptionId = $session->metadata->subscription_id ?? $session->client_reference_id ?? null;

            if ($subscriptionId) {
                $subscription = Subscription::query()->find($subscriptionId);

                if ($subscription) {
                    $payment = Payment::query()
                        ->where('subscription_id', $subscription->getKey())
                        ->where('provider', 'stripe')
                        ->where('provider_reference', $session->id)
                        ->first()
                        ?? $this->payments->createPayment($subscription, 'stripe', (float) $subscription->amount, $session->id);

                    $this->payments->markPaid($payment);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function payWithStripe(Subscription $subscription): RedirectResponse
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

        return redirect()->away($session['url']);
    }

    private function payWithPayPal(Subscription $subscription): RedirectResponse
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

        return redirect()->away($order['approval_url']);
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

    private function afterPayment(Subscription $subscription, string $message): RedirectResponse
    {
        if ($subscription->car) {
            return redirect()
                ->route('autos.show', $subscription->car->slug)
                ->with('success', $message);
        }

        return redirect()
            ->route('panel.membership')
            ->with('success', $message);
    }
}
