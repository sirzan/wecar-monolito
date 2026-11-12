<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Clock3, ShieldCheck } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import {
    paymentMethodIcon,
    type PaymentMethodKey,
    type PaymentMethodOption,
    type PaymentMethodsMap,
} from '@/lib/paymentMethods';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    subscription: { id: string; status: string; payment_method: string | null; amount: number; billing_cycle: string; kind: string; tokens: number };
    plan: { name: string; duration: string; max_cars: number; token_amount?: number | null } | null;
    coupon: { code: string; available: boolean; amount: number };
    car: { slug: string; title: string; price: number; image: string | null } | null;
    methods: PaymentMethodsMap;
    methodOptions?: PaymentMethodOption[];
    spei: { reference: string; bank_name: string | null; clabe: string | null; beneficiary: string | null; amount: number } | null;
    mercadopagoPending?: { payment_id: string | null; status: string; status_detail: string | null; payment_method: string | null } | null;
}>();

const form = useForm({
    method: '' as PaymentMethodKey | '',
    coupon_code: props.coupon.available ? props.coupon.code : '',
});

const options = computed<PaymentMethodOption[]>(() => props.methodOptions ?? []);

const isTokens = props.subscription.kind === 'tokens';
const isMembership = props.subscription.kind === 'membership';
const isAgency = props.subscription.kind === 'agency';

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const pay = (method: PaymentMethodKey) => {
    form.method = method;
    form.post(`/checkout/${props.subscription.id}/pay`);
};
</script>

<template>
    <div>
        <SeoHead :title="isTokens ? 'Pago de tokens — Wecar' : 'Pago seguro — Wecar'" description="Completa tu pago Wecar." noindex />

        <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Pago seguro</p>
                <h1 class="mt-2 text-2xl font-black text-secondary sm:text-3xl">
                    {{ isTokens ? 'Compra tus tokens' : isMembership ? 'Activa tu membresía' : isAgency ? 'Renueva tu plan de agencia' : 'Completa tu publicación' }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ isTokens ? 'Los tokens se acreditarán al confirmar el pago.' : isMembership ? 'La membresía se activa al confirmar el pago.' : isAgency ? 'El plan de tu agencia se renovará al confirmar el pago.' : 'Tu auto se publicará automáticamente al confirmar el pago.' }}
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

                    <div v-if="mercadopagoPending" class="mt-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-900">
                        <div class="flex items-center gap-2 font-bold">
                            <Clock3 class="h-4 w-4 shrink-0" />
                            <span>Pago pendiente en Mercado Pago</span>
                        </div>
                        <p class="mt-1.5 text-xs">
                            Pago <span class="font-mono font-semibold">#{{ mercadopagoPending.payment_id }}</span>
                            <span v-if="mercadopagoPending.payment_method">({{ mercadopagoPending.payment_method }})</span>
                            en espera de acreditación. En cuanto Mercado Pago confirme el pago, activaremos tu publicación automáticamente.
                        </p>
                    </div>

                    <div v-if="spei" class="mt-4 rounded-2xl border border-primary/30 bg-accent/40 p-4">
                        <p class="text-sm font-bold text-secondary">Transferencia SPEI</p>
                        <dl class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                            <div class="flex justify-between"><dt>Referencia</dt><dd class="font-mono font-bold text-secondary">{{ spei.reference }}</dd></div>
                            <div class="flex justify-between"><dt>Banco</dt><dd class="font-semibold text-secondary">{{ spei.bank_name }}</dd></div>
                            <div class="flex justify-between"><dt>CLABE</dt><dd class="font-mono text-xs font-semibold text-secondary">{{ spei.clabe }}</dd></div>
                            <div class="flex justify-between"><dt>Beneficiario</dt><dd class="font-semibold text-secondary">{{ spei.beneficiary }}</dd></div>
                            <div class="flex justify-between"><dt>Monto</dt><dd class="font-bold text-primary">{{ formatPrice(spei.amount) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-[11px] text-muted-foreground">
                            {{ isTokens ? 'Tu pago se verá reflejado en minutos y podrás comenzar a usar tus tokens.' : 'Tu pago se verá reflejado en minutos y podrás comenzar a usar tu producto Wecar.' }}
                        </p>
                    </div>

                    <div v-if="form.errors.method" class="mt-4 flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-semibold text-destructive">
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span>{{ form.errors.method }}</span>
                    </div>

                    <div v-if="options.length" class="mt-5 space-y-3">
                        <div v-for="(option, index) in options" :key="option.key" class="space-y-1">
                            <Button
                                :variant="index === 0 ? 'default' : 'outline'"
                                class="w-full justify-between"
                                size="lg"
                                :disabled="form.processing"
                                @click="pay(option.key)"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <component :is="paymentMethodIcon(option.key)" class="h-4 w-4" />
                                    <span>{{ option.key === 'spei' ? 'Transferencia SPEI' : `Pagar con ${option.label}` }}</span>
                                </span>
                                <span
                                    v-if="option.badge"
                                    class="rounded-full bg-background/20 px-2 py-0.5 text-[10px] font-bold uppercase"
                                >
                                    {{ option.badge }}
                                </span>
                            </Button>
                            <p class="px-1 text-[11px] text-muted-foreground">{{ option.description }}</p>
                        </div>
                    </div>

                    <p v-else class="mt-4 rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground">
                        No hay métodos de pago habilitados o configurados. Contacta a soporte para completar tu publicación.
                    </p>

                    <p class="mt-5 inline-flex items-center gap-2 text-[11px] text-muted-foreground">
                        <ShieldCheck class="h-4 w-4 text-success" /> Pago protegido. Tus datos no se almacenan en Wecar.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
