<script setup lang="ts">
import { CreditCard } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    agency: {
        id: string;
        slug: string;
        name: string;
        plan: string;
        plan_expires_at: string | null;
        max_cars: number;
        cars_count: number;
        plan_name: string | null;
    };
    subscription: { id: string; ends_at: string | null; amount: number } | null;
    renewalPlan: { id: string; name: string; price: number; duration_weeks: number } | null;
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: '2-digit' }) : 'Sin fecha';

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const renew = () => router.post(`${base}/facturacion/renovar`);
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Facturación y plan</h1>
        <p class="mt-1 text-sm text-muted-foreground">Consulta el plan de {{ agency.name }} y su vigencia.</p>

        <div class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent text-accent-foreground"><CreditCard class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-muted-foreground">Plan actual</p>
                    <p class="text-lg font-black capitalize text-secondary">{{ agency.plan_name ?? agency.plan }}</p>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Vigencia</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ formatDate(agency.plan_expires_at) }}</dd>
                </div>
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Autos usados</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ agency.cars_count }} / {{ agency.max_cars }}</dd>
                </div>
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Cupos disponibles</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ Math.max(agency.max_cars - agency.cars_count, 0) }}</dd>
                </div>
            </dl>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.min((agency.cars_count / Math.max(agency.max_cars, 1)) * 100, 100)}%` }" />
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <Button as="a" :href="`${base}/inventario`" variant="outline">Ver inventario</Button>
                <Button as="a" href="/vende">Publicar auto</Button>
                <Button v-if="renewalPlan" variant="outline" @click="renew">Renovar {{ renewalPlan.name }}{{ renewalPlan.price > 0 ? ` · ${formatPrice(renewalPlan.price)}` : '' }}</Button>
            </div>
        </div>
    </div>
</template>
