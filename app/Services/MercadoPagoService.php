<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Payment;
use MercadoPago\Webhook\WebhookSignatureValidator;

class MercadoPagoService
{
    public function enabled(): bool
    {
        return filled(config('services.mercadopago.access_token'))
            && filled(config('services.mercadopago.public_key'));
    }

    public function webhookConfigured(): bool
    {
        return filled(config('services.mercadopago.webhook_secret'));
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.mercadopago.sandbox', false);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function configureSdk(): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Mercado Pago no está configurado.');
        }

        MercadoPagoConfig::setAccessToken((string) config('services.mercadopago.access_token'));
    }

    private function httpClient(): MPHttpClient
    {
        $baseUrl = $this->baseUrl();

        return new class($baseUrl) implements MPHttpClient
        {
            public function __construct(private readonly string $baseUrl) {}

            public function send(MPRequest $request): MPResponse
            {
                $headers = [];

                foreach ($request->getHeaders() ?? [] as $line) {
                    if (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $headers[trim($name)] = trim($value);
                    }
                }

                $timeoutSeconds = max(5, (int) ceil(($request->getConnectionTimeout() ?: 15000) / 1000));
                $pending = Http::withHeaders($headers)
                    ->timeout($timeoutSeconds)
                    ->acceptJson();

                $options = [];

                if ($request->getPayload() !== null) {
                    $options['body'] = $request->getPayload();
                }

                $response = $pending->send($request->getMethod(), $this->baseUrl.$request->getUri(), $options);
                $decoded = $response->json();
                $mpResponse = new MPResponse(
                    $response->status(),
                    is_array($decoded) ? $decoded : [],
                );

                if ($response->failed()) {
                    throw new MPApiException('Error al comunicarse con Mercado Pago.', $mpResponse);
                }

                return $mpResponse;
            }
        };
    }

    /**
     * @return array{id: string, checkout_url: string}
     */
    public function createPreference(
        Subscription $subscription,
        string $successUrl,
        string $pendingUrl,
        string $failureUrl,
        ?string $notificationUrl = null,
    ): array {
        $this->configureSdk();
        $subscription->loadMissing(['plan', 'user']);

        $payload = [
            'items' => [[
                'id' => (string) ($subscription->plan?->key_name ?? $subscription->kind ?? 'wecar-plan'),
                'title' => (string) ($subscription->plan?->name ?? 'Plan Wecar'),
                'quantity' => 1,
                'currency_id' => 'MXN',
                'unit_price' => round((float) $subscription->amount, 2),
            ]],
            'external_reference' => (string) $subscription->getKey(),
            'metadata' => [
                'subscription_id' => (string) $subscription->getKey(),
                'user_id' => (string) $subscription->user_id,
                'plan_id' => (string) ($subscription->plan_id ?? ''),
                'kind' => (string) $subscription->kind,
            ],
            'back_urls' => [
                'success' => $successUrl,
                'pending' => $pendingUrl,
                'failure' => $failureUrl,
            ],
            'statement_descriptor' => 'WECAR',
        ];

        if (filled($subscription->user?->email)) {
            $payload['payer'] = [
                'name' => (string) ($subscription->user->name ?? ''),
                'email' => (string) $subscription->user->email,
            ];
        }

        if (str_starts_with($successUrl, 'https://')) {
            $payload['auto_return'] = 'approved';
        }

        if (filled($notificationUrl) && ! $this->isLocalUrl($notificationUrl)) {
            $payload['notification_url'] = $notificationUrl;
        }

        $client = new PreferenceClient($this->httpClient());
        $preference = $client->create($payload);

        $checkoutUrl = $this->isSandbox()
            ? ($preference->sandbox_init_point ?: $preference->init_point)
            : ($preference->init_point ?: $preference->sandbox_init_point);

        if (blank($checkoutUrl)) {
            throw new \RuntimeException('Mercado Pago no devolvió URL de Checkout Pro.');
        }

        return [
            'id' => (string) ($preference->id ?? ''),
            'checkout_url' => (string) $checkoutUrl,
        ];
    }

    public function findPayment(string|int $paymentId): Payment
    {
        $rawId = trim((string) $paymentId);

        if ($rawId === '' || ! ctype_digit($rawId)) {
            throw new InvalidArgumentException('Identificador de pago de Mercado Pago inválido.');
        }

        $this->configureSdk();
        $client = new PaymentClient($this->httpClient());

        return $client->get((int) $rawId);
    }

    public function isValidWebhookSignature(?string $xSignature, ?string $xRequestId, ?string $dataId): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret', '');

        if ($secret === '') {
            return false;
        }

        $normalizedDataId = $dataId !== null && $dataId !== ''
            ? strtolower(trim($dataId))
            : null;

        try {
            WebhookSignatureValidator::validate($xSignature, $xRequestId, $normalizedDataId, $secret);

            return true;
        } catch (InvalidWebhookSignatureException|InvalidArgumentException) {
            return false;
        }
    }

    private function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.local');
    }
}
