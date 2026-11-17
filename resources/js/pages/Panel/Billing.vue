<script setup lang="ts">
import { Check, CreditCard, ExternalLink, ReceiptText, ShieldCheck, Sparkles } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import { formatMxn } from '@/lib/currency';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    plans: { id: string; key_name: string; name: string; price: number; duration: string; max_cars: number; highlight: boolean; features: string[] | null }[];
    activePlan: { key: string | null; name: string | null; ends_at: string | null; max_cars: number; used_cars: number; subscription_id: string; active: boolean } | null;
    invoices: { id: string; date: string; concept?: string; amount: number; status: string; method?: string | null }[];
}>();

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: '2-digit' }) : 'Sin fecha';

const renewalLabel = (status: string) => ({ paid: 'Pagado', pending: 'Pendiente', failed: 'Fallido', refunded: 'Reembolsado' })[status] ?? status;

const renew = () => router.post('/panel/facturacion/renovar');
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-secondary sm:text-3xl">Facturación</h1>
                <p class="mt-1 text-sm text-muted-foreground">Consulta tu plan, tus pagos y tus métodos de pago.</p>
            </div>
            <Button as="a" href="/panel/metodos-pago" variant="outline" class="gap-2">
                <CreditCard class="h-4 w-4" /> Métodos de pago
            </Button>
        </div>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border bg-gradient-to-r from-primary/10 via-card to-accent/20 p-6">
                <div class="flex items-start gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-soft">
                        <Sparkles class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-muted-foreground">Mi plan</p>
                        <h2 class="mt-1 text-xl font-black text-secondary">{{ activePlan?.name ?? 'Sin plan activo' }}</h2>
                        <p class="mt-1 text-xs font-semibold" :class="activePlan?.active ? 'text-emerald-600' : 'text-amber-600'">
                            {{ activePlan?.active ? 'Activo' : activePlan ? 'Por renovar' : 'Sin suscripción' }}
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Vigencia</p>
                    <p class="mt-1 text-sm font-bold text-secondary">{{ formatDate(activePlan?.ends_at ?? null) }}</p>
                </div>
            </div>

            <div v-if="activePlan" class="grid gap-4 p-6 sm:grid-cols-3">
                <div class="rounded-2xl bg-muted/50 p-4">
                    <p class="text-xs text-muted-foreground">Publicaciones usadas</p>
                    <p class="mt-1 text-lg font-black text-secondary">{{ activePlan.used_cars }} / {{ activePlan.max_cars }}</p>
                </div>
                <div class="rounded-2xl bg-muted/50 p-4">
                    <p class="text-xs text-muted-foreground">Cupos disponibles</p>
                    <p class="mt-1 text-lg font-black text-secondary">{{ Math.max(activePlan.max_cars - activePlan.used_cars, 0) }}</p>
                </div>
                <div class="rounded-2xl bg-muted/50 p-4">
                    <p class="text-xs text-muted-foreground">Estado</p>
                    <p class="mt-1 text-lg font-black" :class="activePlan.active ? 'text-emerald-600' : 'text-destructive'">
                        {{ activePlan.active ? 'Al día' : 'Vencido' }}
                    </p>
                </div>
            </div>

            <div v-if="activePlan && !activePlan.active" class="mx-6 mb-6 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm font-semibold text-destructive">
                Tu plan venció. Tus autos están pausados hasta que renueves.
            </div>

            <div class="flex flex-wrap gap-3 border-t border-border p-6">
                <Button v-if="activePlan" @click="renew">Renovar plan</Button>
                <Button as="a" href="/vende" variant="outline">Publicar un auto</Button>
                <Button as="a" href="/panel/metodos-pago" variant="ghost" class="gap-1.5">
                    Administrar método de pago <ExternalLink class="h-3.5 w-3.5" />
                </Button>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex items-center gap-2">
                <ReceiptText class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-black text-secondary">Historial de pagos</h2>
            </div>

            <div v-if="invoices.length" class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-border text-[10px] uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="py-2 pr-4 font-bold">Fecha</th>
                            <th class="py-2 pr-4 font-bold">Concepto</th>
                            <th class="py-2 pr-4 font-bold">Método</th>
                            <th class="py-2 pr-4 text-right font-bold">Importe</th>
                            <th class="py-2 text-right font-bold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td class="py-3 pr-4 text-muted-foreground">{{ invoice.date }}</td>
                            <td class="py-3 pr-4 font-semibold text-secondary">{{ invoice.concept ?? 'Pago Wecar' }}</td>
                            <td class="py-3 pr-4 capitalize text-muted-foreground">{{ invoice.method ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right font-bold text-secondary">{{ formatMxn(invoice.amount) }}</td>
                            <td class="py-3 text-right">
                                <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-600">{{ renewalLabel(invoice.status) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-4 rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Aún no tienes pagos registrados.</p>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-secondary">Planes disponibles</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Consulta las opciones vigentes para tu inventario.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-5 md:grid-cols-3">
                <div v-for="plan in plans" :key="plan.id" class="rounded-3xl border bg-card p-5 shadow-soft" :class="plan.highlight ? 'border-primary' : 'border-border'">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary">{{ plan.name }}</p>
                    <p class="mt-2 text-2xl font-black text-secondary">{{ plan.price === 0 ? 'Gratis' : formatMxn(plan.price) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ plan.duration }} · hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto' : 'autos' }}</p>
                    <ul v-if="plan.features?.length" class="mt-4 space-y-2 text-xs text-muted-foreground">
                        <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2">
                            <Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" /> {{ feature }}
                        </li>
                    </ul>
                    <Button as="a" href="/vende" class="mt-4 w-full" :variant="plan.highlight ? 'hero' : 'outline'">Elegir plan</Button>
                </div>
            </div>
        </section>

        <p class="flex items-center gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="h-4 w-4 text-emerald-600" />
            Los pagos se procesan en pasarelas externas. WeCar no almacena los datos completos de tu tarjeta.
        </p>
    </div>
</template>
