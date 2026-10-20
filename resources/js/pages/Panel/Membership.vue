<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CalendarClock, Coins, Crown, Sparkles } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    membership: { id: string; ends_at: string | null; status: string } | null;
    membershipPlan: { key_name: string; name: string; price: number; duration: string } | null;
    tokenBalance: number;
    tokenPlans: { id: string; key_name: string; name: string; price: number; token_amount: number }[];
    hasCoupon: boolean;
    transactions: { id: string; amount: number; kind: string; reference: string | null; created_at: string }[];
}>();

const membershipForm = useForm({ plan_key: props.membershipPlan?.key_name ?? 'membresia_ext_499' });
const tokenForm = useForm({ plan_key: props.tokenPlans[0]?.key_name ?? 'search_tokens_5' });

const membershipActive = computed(() => {
    if (!props.membership?.ends_at) return false;
    return new Date(props.membership.ends_at).getTime() > Date.now();
});

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' }) : 'Sin vencimiento';

const subscribe = () => membershipForm.post('/panel/membresia/checkout');
const buyTokens = () => tokenForm.post('/panel/membresia/tokens');
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-secondary sm:text-3xl">Membresía Búsqueda Externa</h1>
            <p class="mt-1 text-sm text-muted-foreground">Gestiona tu acceso al buscador de autos en otras plataformas.</p>
        </div>

        <section class="overflow-hidden rounded-3xl border border-primary/30 bg-card shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border bg-gradient-to-r from-primary/10 via-accent/20 to-primary/5 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary text-primary-foreground shadow">
                        <Crown class="h-6 w-6" />
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-black text-secondary">Búsqueda Inteligente Ilimitada</h2>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="membershipActive ? 'bg-emerald-500 text-white' : 'bg-muted text-muted-foreground'">
                                {{ membershipActive ? 'Activa' : 'Inactiva' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ membershipActive ? `Válida hasta ${formatDate(membership?.ends_at ?? null)}` : '3 búsquedas y 15 vistas gratis al día' }}
                        </p>
                    </div>
                </div>

                <Button v-if="!membershipActive" variant="hero" class="gap-2" :disabled="membershipForm.processing" @click="subscribe">
                    <Sparkles class="h-4 w-4" /> Activar por {{ formatPrice(membershipPlan?.price ?? 499) }}
                </Button>
                <Button v-else variant="outline" class="gap-2" :disabled="membershipForm.processing" @click="subscribe">
                    <CalendarClock class="h-4 w-4" /> Renovar 30 días
                </Button>
            </div>

            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Estado de consumo</p>
                    <p class="mt-1 text-lg font-extrabold text-secondary">{{ membershipActive ? 'Búsquedas ilimitadas' : '3 búsquedas / 15 vistas gratis al día' }}</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Periodo actual</p>
                    <p class="mt-1 text-sm font-bold text-secondary">{{ membershipActive ? `Válido hasta ${formatDate(membership?.ends_at ?? null)}` : 'Sin suscripción activa' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-primary/10 text-primary"><Coins class="h-5 w-5" /></span>
                    <div>
                        <h2 class="text-lg font-black text-secondary">Compra de tokens</h2>
                        <p class="text-xs text-muted-foreground">Saldo actual: {{ tokenBalance }} {{ tokenBalance === 1 ? 'token' : 'tokens' }}</p>
                    </div>
                </div>
                <span v-if="hasCoupon" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">TKNS50 · 50% en tokens</span>
            </div>

            <p class="mt-4 text-sm text-muted-foreground">Cada token permite una búsqueda adicional cuando se acaban tus búsquedas gratis. No caducan.</p>

            <form class="mt-5 space-y-3" @submit.prevent="buyTokens">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="plan in tokenPlans"
                        :key="plan.key_name"
                        class="flex cursor-pointer items-center justify-between rounded-2xl border p-4 transition"
                        :class="tokenForm.plan_key === plan.key_name ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                    >
                        <input v-model="tokenForm.plan_key" type="radio" :value="plan.key_name" class="sr-only" />
                        <div>
                            <p class="text-sm font-black text-secondary">{{ plan.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ plan.token_amount }} tokens · sin vencimiento</p>
                        </div>
                        <p class="text-lg font-black text-primary">{{ formatPrice(plan.price) }}</p>
                    </label>
                </div>
                <Button type="submit" variant="hero" class="w-full gap-2" :disabled="tokenForm.processing">
                    <Coins class="h-4 w-4" /> Comprar tokens
                </Button>
            </form>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <h2 class="text-lg font-black text-secondary">Movimientos de tokens</h2>
            <div v-if="transactions.length" class="mt-4 divide-y divide-border">
                <div v-for="transaction in transactions" :key="transaction.id" class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="font-bold capitalize text-secondary">{{ transaction.kind.replace('_', ' ') }}</p>
                        <p class="text-xs text-muted-foreground">{{ new Date(transaction.created_at).toLocaleString('es-MX') }}</p>
                    </div>
                    <span class="font-black" :class="transaction.amount > 0 ? 'text-emerald-600' : 'text-rose-600'">
                        {{ transaction.amount > 0 ? '+' : '' }}{{ transaction.amount }}
                    </span>
                </div>
            </div>
            <p v-else class="mt-4 text-sm text-muted-foreground">Aún no tienes movimientos de tokens.</p>
        </section>
    </div>
</template>
