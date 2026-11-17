<script setup lang="ts">
import { CreditCard, ReceiptText, ShieldCheck } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import { formatMxn } from '@/lib/currency';

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
    invoices: { id: string; date: string; concept?: string; amount: number; status: string; method?: string | null }[];
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: '2-digit' }) : 'Sin fecha';

const renew = () => router.post(`${base}/facturacion/renovar`);
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
            <h1 class="text-2xl font-black text-secondary sm:text-3xl">Facturación y plan</h1>
            <p class="mt-1 text-sm text-muted-foreground">Administra el plan de {{ agency.name }}.</p>
        </div>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent text-accent-foreground"><CreditCard class="h-5 w-5" /></span>
                    <div>
                        <p class="text-xs font-semibold text-muted-foreground">Plan actual</p>
                        <p class="text-lg font-black capitalize text-secondary">{{ agency.plan_name ?? agency.plan }}</p>
                    </div>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="agency.plan_expires_at ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600'">
                    {{ agency.plan_expires_at ? 'Con vigencia' : 'Sin fecha de vigencia' }}
                </span>
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
                <Button v-if="renewalPlan" @click="renew">Renovar {{ renewalPlan.name }}<template v-if="renewalPlan.price > 0"> · {{ formatMxn(renewalPlan.price) }}</template></Button>
                <Button as="a" :href="`${base}/inventario`" variant="outline">Ver inventario</Button>
                <Button as="a" href="/panel/metodos-pago" variant="ghost">Métodos de pago</Button>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex items-center gap-2">
                <ReceiptText class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-black text-secondary">Historial de pagos de la agencia</h2>
            </div>
            <div v-if="invoices.length" class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-border text-[10px] uppercase tracking-wider text-muted-foreground">
                        <tr><th class="py-2 pr-4">Fecha</th><th class="py-2 pr-4">Concepto</th><th class="py-2 pr-4">Método</th><th class="py-2 text-right">Importe</th><th class="py-2 text-right">Estado</th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td class="py-3 pr-4 text-muted-foreground">{{ invoice.date }}</td>
                            <td class="py-3 pr-4 font-semibold text-secondary">{{ invoice.concept ?? 'Plan de agencia' }}</td>
                            <td class="py-3 pr-4 capitalize text-muted-foreground">{{ invoice.method ?? '—' }}</td>
                            <td class="py-3 text-right font-bold text-secondary">{{ formatMxn(invoice.amount) }}</td>
                            <td class="py-3 text-right"><span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-600">{{ invoice.status }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-4 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Aún no hay pagos registrados para esta agencia.</p>
        </section>

        <p class="flex items-center gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="h-4 w-4 text-emerald-600" />
            Los pagos se procesan en pasarelas externas. WeCar no almacena los datos completos de tu tarjeta.
        </p>
    </div>
</template>
