<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private ?StripeClient $client;

    public function __construct()
    {
        $secret = config('services.stripe.secret');
        $this->client = $secret ? new StripeClient($secret) : null;
    }

    public function enabled(): bool
    {
        return $this->client !== null;
    }

    /**
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Subscription $subscription, string $successUrl, string $cancelUrl): array
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe no está configurado.');
        }

        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $subscription->user?->email,
            'client_reference_id' => $subscription->getKey(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'mxn',
                    'unit_amount' => (int) round(((float) $subscription->amount) * 100),
                    'product_data' => [
                        'name' => $subscription->plan?->name ?? 'Plan Wecar',
                        'description' => 'Suscripción Wecar',
                    ],
                ],
            ]],
            'metadata' => [
                'subscription_id' => $subscription->getKey(),
                'user_id' => $subscription->user_id,
                'kind' => $subscription->kind,
                'plan_key' => $subscription->plan?->key_name,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return ['id' => $session->id, 'url' => (string) $session->url];
    }

    public function retrieveSession(string $sessionId): Session
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe no está configurado.');
        }

        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    public function constructEvent(string $payload, string $signature): object
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe webhook secret no configurado.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    public function ensureCustomer(User $user): string
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe no está configurado.');
        }

        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => $user->getKey()],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return (string) $customer->id;
    }

    public function billingPortalUrl(User $user, string $returnUrl): string
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe no está configurado.');
        }

        $customerId = $this->ensureCustomer($user);

        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    /**
     * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
     */
    public function cards(User $user): array
    {
        if (! $this->client || ! $user->stripe_customer_id) {
            return [];
        }

        $customerId = $user->stripe_customer_id;
        $methods = $this->client->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ]);

        $customer = $this->client->customers->retrieve($customerId, ['expand' => ['invoice_settings.default_payment_method']]);
        $defaultId = $customer->invoice_settings?->default_payment_method?->id;

        return collect($methods->data)
            ->map(fn ($method) => [
                'id' => $method->id,
                'brand' => (string) ($method->card->brand ?? ''),
                'last4' => (string) ($method->card->last4 ?? ''),
                'exp_month' => (int) ($method->card->exp_month ?? 0),
                'exp_year' => (int) ($method->card->exp_year ?? 0),
                'is_default' => $method->id === $defaultId,
            ])
            ->values()
            ->all();
    }

    public function detachCard(string $paymentMethodId): void
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe no está configurado.');
        }

        $this->client->paymentMethods->detach($paymentMethodId);
    }
}
