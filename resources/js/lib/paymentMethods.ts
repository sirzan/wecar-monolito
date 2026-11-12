import { Banknote, CreditCard, Landmark, Wallet } from '@lucide/vue';
import type { Component } from 'vue';

export type PaymentMethodKey = 'mercadopago' | 'stripe' | 'paypal' | 'spei';

export interface PaymentMethodOption {
    key: PaymentMethodKey;
    label: string;
    description: string;
    badge: string | null;
}

export type PaymentMethodsMap = Partial<Record<PaymentMethodKey, boolean>>;

const ICONS: Record<PaymentMethodKey, Component> = {
    mercadopago: Wallet,
    stripe: CreditCard,
    paypal: Landmark,
    spei: Banknote,
};

const PROVIDER_LABELS: Record<string, string> = {
    mercadopago: 'Mercado Pago',
    stripe: 'Stripe',
    paypal: 'PayPal',
    spei: 'SPEI',
};

export const paymentMethodIcon = (key: string): Component =>
    ICONS[key as PaymentMethodKey] ?? CreditCard;

export const paymentProviderLabel = (provider: string): string =>
    PROVIDER_LABELS[provider] ?? provider.toUpperCase();
