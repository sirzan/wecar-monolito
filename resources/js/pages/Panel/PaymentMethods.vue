<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CreditCard, Landmark, ShieldCheck, Trash2, Wallet } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    cards: { id: string; brand: string; last4: string; exp_month: number; exp_year: number; is_default: boolean }[];
    methods: { stripe: boolean; paypal: boolean; spei: boolean };
}>();

const openPortal = () => router.post('/panel/metodos-pago/portal');

const removeCard = (id: string) => {
    if (!window.confirm('¿Eliminar esta tarjeta?')) {
        return;
    }

    router.delete(`/panel/metodos-pago/${id}`, { preserveScroll: true });
};

const brandLabel = (brand: string) => {
    const labels: Record<string, string> = {
        visa: 'Visa',
        mastercard: 'Mastercard',
        amex: 'American Express',
        discover: 'Discover',
        jcb: 'JCB',
        diners: 'Diners Club',
        unionpay: 'UnionPay',
    };

    return labels[brand] ?? brand.toUpperCase();
};
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-secondary sm:text-3xl">Métodos de pago</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Administra tus tarjetas y consulta los métodos disponibles para pagar planes y servicios.
            </p>
        </div>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <CreditCard class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="font-bold text-secondary">Tarjetas guardadas</h2>
                        <p class="text-xs text-muted-foreground">Gestionadas de forma segura con Stripe.</p>
                    </div>
                </div>
                <Button v-if="methods.stripe" variant="outline" :disabled="!methods.stripe" @click="openPortal">
                    Administrar tarjetas
                </Button>
            </div>

            <div v-if="!methods.stripe" class="mt-5 rounded-2xl border border-dashed border-border p-5 text-sm text-muted-foreground">
                Stripe no está habilitado. Actívalo en Configuración → Pagos para guardar tarjetas.
            </div>

            <div v-else-if="cards.length" class="mt-5 space-y-3">
                <div
                    v-for="card in cards"
                    :key="card.id"
                    class="flex items-center justify-between rounded-2xl border border-border p-4"
                >
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-14 place-items-center rounded-lg bg-muted text-xs font-black uppercase text-secondary">
                            {{ brandLabel(card.brand) }}
                        </span>
                        <div>
                            <p class="text-sm font-bold text-secondary">•••• {{ card.last4 }}</p>
                            <p class="text-xs text-muted-foreground">
                                Vence {{ String(card.exp_month).padStart(2, '0') }}/{{ card.exp_year }}
                                <span v-if="card.is_default" class="ml-2 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Predeterminada</span>
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-lg text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                        aria-label="Eliminar tarjeta"
                        @click="removeCard(card.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-else class="mt-5 rounded-2xl border border-dashed border-border p-6 text-center">
                <Wallet class="mx-auto h-8 w-8 text-muted-foreground" />
                <p class="mt-2 text-sm text-muted-foreground">Aún no tienes tarjetas guardadas.</p>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <h2 class="font-bold text-secondary">Métodos disponibles</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border p-4" :class="methods.stripe ? 'border-border' : 'border-dashed opacity-60'">
                    <CreditCard class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-bold text-secondary">Tarjeta (Stripe)</p>
                    <p class="mt-1 text-xs text-muted-foreground">Pago seguro con tarjeta de crédito o débito.</p>
                </div>
                <div class="rounded-2xl border p-4" :class="methods.paypal ? 'border-border' : 'border-dashed opacity-60'">
                    <Wallet class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-bold text-secondary">PayPal</p>
                    <p class="mt-1 text-xs text-muted-foreground">Paga con tu saldo o cuenta PayPal.</p>
                </div>
                <div class="rounded-2xl border p-4" :class="methods.spei ? 'border-border' : 'border-dashed opacity-60'">
                    <Landmark class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-bold text-secondary">SPEI</p>
                    <p class="mt-1 text-xs text-muted-foreground">Transferencia bancaria con referencia.</p>
                </div>
            </div>

            <p class="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
                <ShieldCheck class="h-4 w-4 text-success" /> No almacenamos los datos de tu tarjeta; los gestiona Stripe.
            </p>
        </section>
    </div>
</template>
