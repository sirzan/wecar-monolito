<?php

namespace App\Services;

use App\Models\SiteSetting;

class PaymentMethodsService
{
    /**
     * @var array<string, array{label: string, description: string, badge: string|null, setting_key: string, hint: string}>
     */
    private const CATALOG = [
        'mercadopago' => [
            'label' => 'Mercado Pago',
            'description' => 'Tarjetas, MSI, OXXO, SPEI y saldo Mercado Pago',
            'badge' => 'Recomendado',
            'setting_key' => 'mercadopago_enabled',
            'hint' => 'Define MERCADOPAGO_PUBLIC_KEY y MERCADOPAGO_ACCESS_TOKEN en .env.',
        ],
        'stripe' => [
            'label' => 'Tarjeta (Stripe)',
            'description' => 'Visa, Mastercard y American Express',
            'badge' => null,
            'setting_key' => 'stripe_enabled',
            'hint' => 'Define STRIPE_KEY y STRIPE_SECRET en .env.',
        ],
        'paypal' => [
            'label' => 'PayPal',
            'description' => 'Paga con tu cuenta o tarjeta desde PayPal',
            'badge' => null,
            'setting_key' => 'paypal_enabled',
            'hint' => 'Define PAYPAL_CLIENT_ID y PAYPAL_CLIENT_SECRET en .env.',
        ],
        'spei' => [
            'label' => 'Transferencia SPEI',
            'description' => 'Pago por CLABE interbancaria con validación manual',
            'badge' => 'Sin comisión',
            'setting_key' => 'spei_enabled',
            'hint' => 'Captura banco, beneficiario y CLABE de 18 dígitos.',
        ],
    ];

    public function __construct(
        private readonly MercadoPagoService $mercadopago,
        private readonly StripeService $stripe,
        private readonly PayPalService $paypal,
        private readonly SpeiService $spei,
    ) {}

    /**
     * @return list<string>
     */
    public function allKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * @return array<string, bool>
     */
    public function configuredMap(?array $settings = null): array
    {
        $settings = $this->resolveSettings($settings);

        return [
            'mercadopago' => $this->mercadopago->enabled(),
            'stripe' => $this->stripe->enabled(),
            'paypal' => $this->paypal->enabled(),
            'spei' => $this->spei->configured($settings),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function frontendMap(?array $settings = null): array
    {
        $settings = $this->resolveSettings($settings);
        $configured = $this->configuredMap($settings);
        $result = [];

        foreach (self::CATALOG as $key => $meta) {
            $result[$key] = (bool) ($settings[$meta['setting_key']] ?? false) && $configured[$key];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function availableKeys(?array $settings = null): array
    {
        return array_keys(array_filter($this->frontendMap($settings)));
    }

    public function isAvailable(string $method, ?array $settings = null): bool
    {
        return $this->frontendMap($settings)[$method] ?? false;
    }

    /**
     * @return list<array{key: string, label: string, description: string, badge: string|null}>
     */
    public function options(?array $settings = null): array
    {
        $available = $this->frontendMap($settings);
        $options = [];

        foreach (self::CATALOG as $key => $meta) {
            if (! ($available[$key] ?? false)) {
                continue;
            }

            $options[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'badge' => $meta['badge'],
            ];
        }

        return $options;
    }

    /**
     * @return array<string, array{configured: bool, enabled: bool, active: bool, hint: string}>
     */
    public function adminStatus(?array $settings = null): array
    {
        $settings = $this->resolveSettings($settings);
        $configured = $this->configuredMap($settings);
        $status = [];

        foreach (self::CATALOG as $key => $meta) {
            $enabled = (bool) ($settings[$meta['setting_key']] ?? false);

            $status[$key] = [
                'configured' => $configured[$key],
                'enabled' => $enabled,
                'active' => $enabled && $configured[$key],
                'hint' => $meta['hint'],
            ];
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(?array $settings = null): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        $stored = SiteSetting::get('payments', []);

        return is_array($stored) ? $stored : [];
    }
}
