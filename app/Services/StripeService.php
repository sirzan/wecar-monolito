<?php

namespace App\Services;

use App\Models\Subscription;
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
}
