<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Services\MercadoPagoService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentMethodsService;
use App\Services\PaymentService;
use App\Services\PayPalService;
use App\Services\SearchCouponService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MercadoPago\Resources\Payment as MercadoPagoPayment;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly PayPalService $paypal,
        private readonly MercadoPagoService $mercadopago,
        private readonly PaymentService $payments,
        private readonly SearchCouponService $coupons,
        private readonly PaymentMethodsService $paymentMethods,
        private readonly PaymentCheckoutService $checkout,
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

        $kind = $subscription->kind === 'agency' ? 'agency' : ($subscription->plan?->type ?? $subscription->kind);
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

        $mercadopagoPending = $subscription->payments()
            ->where('provider', 'mercadopago')
            ->where('status', 'pending')
            ->whereNotNull('metadata->mp_payment_id')
            ->latest()
            ->first();

        return Inertia::render('Public/Checkout', [
            'subscription' => [
                'id' => $subscription->getKey(),
                'status' => $subscription->status,
                'payment_method' => $subscription->payment_method,
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
            'methods' => $this->paymentMethods->frontendMap($payments),
            'methodOptions' => $this->paymentMethods->options($payments),
            'spei' => $speiPayment ? [
                'reference' => $speiPayment->provider_reference,
                'bank_name' => data_get($speiPayment->metadata, 'bank_name'),
                'clabe' => data_get($speiPayment->metadata, 'clabe'),
                'beneficiary' => data_get($speiPayment->metadata, 'beneficiary'),
                'amount' => (float) $speiPayment->amount,
            ] : null,
            'mercadopagoPending' => $mercadopagoPending ? [
                'payment_id' => data_get($mercadopagoPending->metadata, 'mp_payment_id'),
                'status' => data_get($mercadopagoPending->metadata, 'mp_status', 'pending'),
                'status_detail' => data_get($mercadopagoPending->metadata, 'status_detail'),
                'payment_method' => data_get($mercadopagoPending->metadata, 'payment_method_id'),
            ] : null,
        ]);
    }

    public function pay(Request $request, Subscription $subscription): SymfonyResponse|RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        if (in_array($subscription->status, ['active', 'completed'], true)) {
            return $this->afterPayment($subscription, 'Tu compra ya está confirmada.');
        }

        $validated = $request->validate([
            'method' => ['required', Rule::in($this->paymentMethods->allKeys())],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->checkout->start(
            $subscription,
            $request->user(),
            $validated['method'],
            $validated['coupon_code'] ?? null,
        );
    }

    public function stripeSuccess(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        $sessionId = (string) $request->query('session_id');
        abort_if($sessionId === '', 400);

        $session = $this->stripe->retrieveSession($sessionId);

        abort_unless(
            $session->payment_status === 'paid' && $this->stripeSessionMatches($session, $subscription),
            422,
            'La sesión de pago no corresponde a esta suscripción.',
        );

        $payment = Payment::query()
            ->where('provider', 'stripe')
            ->where('provider_reference', $sessionId)
            ->first();

        abort_unless(! $payment || $payment->subscription_id === $subscription->getKey(), 422);

        $payment ??= $this->payments->createPayment($subscription, 'stripe', (float) $subscription->amount, $sessionId);

        $this->payments->markPaid($payment);

        return $this->afterPayment($subscription->fresh(), 'Pago confirmado. Tu auto ya está publicado.');
    }

    public function mercadopagoReturn(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        if (in_array($subscription->status, ['active', 'completed'], true)) {
            return $this->afterPayment($subscription, 'Tu pago ya está confirmado.');
        }

        $status = strtolower((string) ($request->query('status') ?? $request->query('collection_status') ?? ''));
        $paymentId = trim((string) ($request->query('payment_id') ?? $request->query('collection_id') ?? ''));
        $preferenceId = trim((string) ($request->query('preference_id') ?? ''));

        if ($paymentId === '' || strtolower($paymentId) === 'null' || ! ctype_digit($paymentId)) {
            if (in_array($status, ['pending', 'in_process'], true)) {
                return redirect()
                    ->route('checkout.show', $subscription)
                    ->with('success', 'Tu pago en Mercado Pago está pendiente de acreditación. Activaremos tu servicio en cuanto se confirme.');
            }

            return redirect()
                ->route('checkout.show', $subscription)
                ->with('error', 'El pago en Mercado Pago no se completó. Puedes intentar nuevamente o elegir otro método.');
        }

        $mpPayment = $this->mercadopago->findPayment($paymentId);

        abort_unless(
            $this->mercadopagoPaymentMatches($mpPayment, $subscription),
            422,
            'El pago de Mercado Pago no corresponde a esta suscripción.',
        );

        $metadata = $this->mercadopagoMetadata($mpPayment, $preferenceId ?: null);
        $mpStatus = strtolower((string) ($mpPayment->status ?? ''));

        if ($mpStatus === 'approved') {
            $this->checkout->settleMercadoPagoPayment($subscription, (string) $mpPayment->id, $metadata, $preferenceId ?: null);

            $message = $subscription->car
                ? 'Pago confirmado con Mercado Pago. Tu auto ya está publicado.'
                : 'Pago confirmado con Mercado Pago. Tu compra está activa.';

            return $this->afterPayment($subscription->fresh(), $message);
        }

        if (in_array($mpStatus, ['pending', 'in_process', 'authorized'], true)) {
            $this->checkout->recordMercadoPagoPending($subscription, (string) $mpPayment->id, $metadata, $preferenceId ?: null);

            return redirect()
                ->route('checkout.show', $subscription)
                ->with('success', 'Tu pago está pendiente de acreditación en Mercado Pago. En cuanto se confirme, activaremos tu publicación.');
        }

        return redirect()
            ->route('checkout.show', $subscription)
            ->with('error', 'Mercado Pago no aprobó el pago. Intenta nuevamente o elige otro método disponible.');
    }

    public function mercadopagoWebhook(Request $request): JsonResponse
    {
        if (! $this->mercadopago->webhookConfigured()) {
            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        $queryDataId = $request->query('data.id') ?? $request->query('data_id');
        $signatureDataId = is_scalar($queryDataId) ? (string) $queryDataId : null;

        if (! $this->mercadopago->isValidWebhookSignature(
            $request->header('x-signature'),
            $request->header('x-request-id'),
            $signatureDataId,
        )) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $topic = strtolower((string) ($request->input('type') ?? $request->query('type') ?? $request->input('topic') ?? $request->query('topic') ?? ''));
        $action = strtolower((string) $request->input('action', ''));

        if ($topic !== 'payment' && ! str_starts_with($action, 'payment.')) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $rawPaymentId = $request->input('data.id') ?? $signatureDataId;

        if (! is_scalar($rawPaymentId) || ! ctype_digit(trim((string) $rawPaymentId))) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $mpPayment = $this->mercadopago->findPayment((string) $rawPaymentId);
        $subscriptionId = $mpPayment->external_reference ?? null;
        $subscription = filled($subscriptionId) ? Subscription::query()->find($subscriptionId) : null;

        if (! $subscription || ! $this->mercadopagoPaymentMatches($mpPayment, $subscription)) {
            report(new \RuntimeException('Mercado Pago payment rejected because it does not match a subscription.'));

            return response()->json(['error' => 'Invalid payment association'], 422);
        }

        $mpPaymentId = (string) $mpPayment->id;
        $existing = Payment::query()
            ->where('provider', 'mercadopago')
            ->where('provider_reference', $mpPaymentId)
            ->first();

        if ($existing && $existing->subscription_id !== $subscription->getKey()) {
            report(new \RuntimeException('Mercado Pago payment reference is associated with another subscription.'));

            return response()->json(['error' => 'Invalid payment association'], 422);
        }

        $metadata = $this->mercadopagoMetadata($mpPayment);
        $mpStatus = strtolower((string) ($mpPayment->status ?? ''));

        if ($mpStatus === 'approved') {
            $this->checkout->settleMercadoPagoPayment($subscription, $mpPaymentId, $metadata);
        } elseif (in_array($mpStatus, ['pending', 'in_process', 'authorized'], true)) {
            $this->checkout->recordMercadoPagoPending($subscription, $mpPaymentId, $metadata);
        }

        return response()->json(['received' => true]);
    }

    public function paypalReturn(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);

        $orderId = (string) $request->query('token');
        abort_if($orderId === '', 400);

        $existingPayment = Payment::query()
            ->where('provider', 'paypal')
            ->where('provider_reference', $orderId)
            ->first();

        if ($existingPayment?->status === 'paid') {
            abort_unless($existingPayment->subscription_id === $subscription->getKey(), 422);

            return $this->afterPayment($subscription->fresh(), 'Tu pago ya está confirmado.');
        }

        abort_unless(! $existingPayment || $existingPayment->subscription_id === $subscription->getKey(), 422);

        $capture = $this->paypal->captureOrder($orderId);

        abort_unless(
            ($capture['status'] ?? null) === 'COMPLETED'
                && $this->paypalCaptureMatches($capture, $subscription),
            422,
            'La orden de pago no corresponde a esta suscripción.',
        );

        $payment = $existingPayment
            ?? $this->payments->createPayment($subscription, 'paypal', (float) $subscription->amount, $orderId);

        $this->payments->markPaid($payment);

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

            $subscription = $subscriptionId ? Subscription::query()->find($subscriptionId) : null;

            if (! $subscription || ! $this->stripeSessionMatches($session, $subscription)) {
                report(new \RuntimeException('Stripe session rejected because it does not match a subscription.'));

                return response()->json(['error' => 'Invalid payment association'], 422);
            }

            $payment = Payment::query()
                ->where('provider', 'stripe')
                ->where('provider_reference', $session->id)
                ->first();

            if ($payment && $payment->subscription_id !== $subscription->getKey()) {
                report(new \RuntimeException('Stripe payment reference is associated with another subscription.'));

                return response()->json(['error' => 'Invalid payment association'], 422);
            }

            $payment ??= $this->payments->createPayment($subscription, 'stripe', (float) $subscription->amount, $session->id);

            $this->payments->markPaid($payment);
        }

        return response()->json(['received' => true]);
    }

    private function mercadopagoPaymentMatches(MercadoPagoPayment $mpPayment, Subscription $subscription): bool
    {
        return (string) ($mpPayment->external_reference ?? '') === (string) $subscription->getKey()
            && strtoupper((string) ($mpPayment->currency_id ?? '')) === 'MXN'
            && number_format((float) ($mpPayment->transaction_amount ?? 0), 2, '.', '') === number_format((float) $subscription->amount, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function mercadopagoMetadata(MercadoPagoPayment $mpPayment, ?string $preferenceId = null): array
    {
        return array_filter([
            'preference_id' => $preferenceId,
            'mp_status' => $mpPayment->status,
            'status_detail' => $mpPayment->status_detail,
            'payment_method_id' => $mpPayment->payment_method_id,
            'payment_type_id' => $mpPayment->payment_type_id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function stripeSessionMatches(object $session, Subscription $subscription): bool
    {
        $expectedAmount = (int) round(((float) $subscription->amount) * 100);

        return (string) ($session->client_reference_id ?? '') === $subscription->getKey()
            && (string) data_get($session, 'metadata.subscription_id') === $subscription->getKey()
            && (string) data_get($session, 'metadata.user_id') === $subscription->user_id
            && strtolower((string) ($session->currency ?? '')) === 'mxn'
            && (int) ($session->amount_total ?? 0) === $expectedAmount;
    }

    /**
     * @param  array<string, mixed>  $capture
     */
    private function paypalCaptureMatches(array $capture, Subscription $subscription): bool
    {
        $purchaseUnit = data_get($capture, 'purchase_units.0', []);
        $captureData = data_get($purchaseUnit, 'payments.captures.0', []);
        $reference = data_get($purchaseUnit, 'reference_id')
            ?? data_get($purchaseUnit, 'custom_id')
            ?? data_get($captureData, 'custom_id');
        $amount = data_get($captureData, 'amount.value') ?? data_get($purchaseUnit, 'amount.value');
        $currency = data_get($captureData, 'amount.currency_code') ?? data_get($purchaseUnit, 'amount.currency_code');

        return (string) $reference === $subscription->getKey()
            && number_format((float) $amount, 2, '.', '') === number_format((float) $subscription->amount, 2, '.', '')
            && strtoupper((string) $currency) === 'MXN';
    }

    private function afterPayment(Subscription $subscription, string $message): RedirectResponse
    {
        if ($subscription->car) {
            return redirect()
                ->route('autos.show', $subscription->car->slug)
                ->with('success', $message);
        }

        if ($subscription->kind === 'agency' && $subscription->agency) {
            return redirect()
                ->route('panel.agency.billing', $subscription->agency)
                ->with('success', $message);
        }

        if ($subscription->kind === 'publication') {
            return redirect()
                ->route('panel.billing')
                ->with('success', $message);
        }

        return redirect()
            ->route('panel.membership')
            ->with('success', $message);
    }
}
