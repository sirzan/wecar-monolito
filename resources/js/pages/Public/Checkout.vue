<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Banknote, CreditCard, Landmark, ShieldCheck } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    subscription: { id: string; status: string; amount: number; billing_cycle: string; kind: string; tokens: number };
    plan: { name: string; duration: string; max_cars: number; token_amount?: number | null } | null;
    coupon: { code: string; available: boolean; amount: number };
    car: { slug: string; title: string; price: number; image: string | null } | null;
    methods: { stripe: boolean; paypal: boolean; spei: boolean };
    spei: { reference: string; bank_name: string | null; clabe: string | null; beneficiary: string | null; amount: number } | null;
}>();

const form = useForm({ method: '' as 'stripe' | 'paypal' | 'spei' | '', coupon_code: props.coupon.available ? props.coupon.code : '' });

const isTokens = props.subscription.kind === 'tokens';
const isMembership = props.subscription.kind === 'membership';

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const pay = (method: 'stripe' | 'paypal' | 'spei') => {
    form.method = method;
    form.post(`/checkout/${props.subscription.id}/pay`);
};
</script>

<template>
    <SeoHead title="Pago de publicación — Wecar" description="Completa el pago de tu plan Wecar." noindex />

    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Pago seguro</p>
            <h1 class="mt-2 text-2xl font-black text-secondary sm:text-3xl">
                {{ isTokens ? 'Compra tus tokens' : isMembership ? 'Activa tu membresía' : 'Completa tu publicación' }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ isTokens ? 'Los tokens se acreditarán al confirmar el pago.' : isMembership ? 'La membresía se activa al confirmar el pago.' : 'Tu auto se publicará automáticamente al confirmar el pago.' }}
            </p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.1fr_1fr]">
            <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                <h2 class="font-black text-secondary">Resumen</h2>
                <div v-if="car" class="mt-4 flex items-center gap-3 rounded-2xl bg-muted/50 p-3">
                    <img v-if="car.image" :src="car.image" :alt="car.title" class="h-16 w-24 rounded-lg object-cover" />
                    <div>
                        <p class="font-semibold text-secondary">{{ car.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatPrice(car.price) }}</p>
                    </div>
                </div>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">Plan</dt><dd class="font-bold text-secondary">{{ plan?.name ?? 'Plan Wecar' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">Duración</dt><dd class="font-bold text-secondary">{{ plan?.duration ?? '30 días' }}</dd></div>
                    <div v-if="isTokens" class="flex justify-between"><dt class="text-muted-foreground">Tokens</dt><dd class="font-bold text-secondary">{{ subscription.tokens }}</dd></div>
                    <div v-else class="flex justify-between"><dt class="text-muted-foreground">Cupos</dt><dd class="font-bold text-secondary">{{ plan?.max_cars ?? 1 }}</dd></div>
                    <div v-if="isTokens && coupon.available" class="flex justify-between"><dt class="text-muted-foreground">Cupón {{ coupon.code }}</dt><dd class="font-bold text-emerald-600">-50%</dd></div>
                    <div class="flex justify-between border-t border-border pt-3"><dt class="text-muted-foreground">Total</dt><dd class="text-xl font-black text-primary">{{ formatPrice(coupon.available && isTokens ? coupon.amount : subscription.amount) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                <h2 class="font-black text-secondary">Método de pago</h2>

                <p v-if="isTokens && coupon.available" class="mt-3 rounded-xl border border-primary/30 bg-primary/5 p-3 text-xs font-semibold text-primary">
                    Cupón {{ coupon.code }} aplicado: 50% de descuento en tokens.
                </p>

                <div v-if="spei" class="mt-4 rounded-2xl border border-primary/30 bg-accent/40 p-4">
                    <p class="text-sm font-bold text-secondary">Transferencia SPEI</p>
                    <dl class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                        <div class="flex justify-between"><dt>Referencia</dt><dd class="font-mono font-bold text-secondary">{{ spei.reference }}</dd></div>
                        <div class="flex justify-between"><dt>Banco</dt><dd class="font-semibold text-secondary">{{ spei.bank_name }}</dd></div>
                        <div class="flex justify-between"><dt>CLABE</dt><dd class="font-mono text-xs font-semibold text-secondary">{{ spei.clabe }}</dd></div>
                        <div class="flex justify-between"><dt>Beneficiario</dt><dd class="font-semibold text-secondary">{{ spei.beneficiary }}</dd></div>
                        <div class="flex justify-between"><dt>Monto</dt><dd class="font-bold text-primary">{{ formatPrice(spei.amount) }}</dd></div>
                    </dl>
                    <p class="mt-3 text-[11px] text-muted-foreground">Envía tu comprobante a hola@wecar.mx. La publicación se activa al validar el pago.</p>
                </div>

                <div class="mt-5 space-y-3">
                    <Button v-if="methods.stripe" class="w-full" size="lg" :disabled="form.processing" @click="pay('stripe')">
                        <CreditCard class="h-4 w-4" /> Pagar con tarjeta
                    </Button>
                    <Button v-if="methods.paypal" variant="outline" class="w-full" size="lg" :disabled="form.processing" @click="pay('paypal')">
                        <Landmark class="h-4 w-4" /> Pagar con PayPal
                    </Button>
                    <Button v-if="methods.spei" variant="outline" class="w-full" size="lg" :disabled="form.processing" @click="pay('spei')">
                        <Banknote class="h-4 w-4" /> Transferencia SPEI
                    </Button>
                </div>

                <p v-if="!methods.stripe && !methods.paypal && !methods.spei" class="mt-4 rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground">
                    No hay métodos de pago habilitados. Contacta a soporte para completar tu publicación.
                </p>

                <p class="mt-5 inline-flex items-center gap-2 text-[11px] text-muted-foreground">
                    <ShieldCheck class="h-4 w-4 text-success" /> Pago protegido. Tus datos no se almacenan en Wecar.
                </p>
            </div>
        </div>
    </section>
</template>
