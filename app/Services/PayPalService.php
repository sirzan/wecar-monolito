<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\Http;

class PayPalService
{
    public function enabled(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paypal.base_url'), '/');
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.client_secret'),
            )
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();

        return (string) $response->json('access_token');
    }

    /**
     * @return array{id: string, approval_url: string}
     */
    public function createOrder(Subscription $subscription, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $subscription->getKey(),
                    'description' => $subscription->plan?->name ?? 'Plan Wecar',
                    'custom_id' => $subscription->getKey(),
                    'amount' => [
                        'currency_code' => 'MXN',
                        'value' => number_format((float) $subscription->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => 'Wecar',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        $response->throw();

        $approval = collect($response->json('links', []))->firstWhere('rel', 'approve');

        return [
            'id' => (string) $response->json('id'),
            'approval_url' => (string) ($approval['href'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture");

        $response->throw();

        return $response->json();
    }
}
