<script setup lang="ts">
import { Check, CreditCard } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    plans: { id: string; key_name: string; name: string; price: number; duration: string; max_cars: number; highlight: boolean; features: string[] | null }[];
    activePlan: { key: string | null; name: string | null; ends_at: string | null; max_cars: number; used_cars: number; subscription_id: string; active: boolean } | null;
    invoices: { id: string; date: string; amount: number; status: string }[];
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: '2-digit' }) : 'Sin fecha';

const renew = () => router.post('/panel/facturacion/renovar');
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Facturación</h1>
        <p class="mt-1 text-sm text-muted-foreground">Consulta tu plan y tus pagos.</p>

        <div class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent text-accent-foreground"><CreditCard class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-muted-foreground">{{ activePlan?.active ? 'Plan actual' : 'Plan por renovar' }}</p>
                    <p class="text-lg font-black text-secondary">{{ activePlan?.name ?? 'Sin plan activo' }}</p>
                </div>
            </div>
            <dl v-if="activePlan" class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Vigencia</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ formatDate(activePlan.ends_at) }}</dd>
                </div>
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Autos usados</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ activePlan.used_cars }} / {{ activePlan.max_cars }}</dd>
                </div>
                <div class="rounded-xl bg-muted/50 p-4">
                    <dt class="text-xs text-muted-foreground">Cupos disponibles</dt>
                    <dd class="mt-1 font-bold text-secondary">{{ Math.max(activePlan.max_cars - activePlan.used_cars, 0) }}</dd>
                </div>
            </dl>
            <Button v-if="activePlan" class="mt-5" @click="renew">Renovar plan</Button>
            <p v-if="activePlan && !activePlan.active" class="mt-2 text-xs font-bold text-destructive">Tu plan venció. Tus autos están pausados hasta que renueves.</p>
        </div>

        <h2 class="mt-8 text-xl font-black text-secondary">Planes disponibles</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-3">
            <div v-for="plan in plans" :key="plan.id" class="rounded-3xl border bg-card p-6 shadow-soft" :class="plan.highlight ? 'border-primary' : 'border-border'">
                <p class="text-sm font-bold uppercase tracking-wide text-primary">{{ plan.name }}</p>
                <p class="mt-3 text-3xl font-black text-secondary">{{ plan.price === 0 ? 'Gratis' : formatPrice(plan.price) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ plan.duration }} · hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto' : 'autos' }}</p>
                <ul v-if="plan.features?.length" class="mt-4 space-y-2 text-sm text-muted-foreground">
                    <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2">
                        <Check class="mt-0.5 h-4 w-4 text-success" /> {{ feature }}
                    </li>
                </ul>
                <Button as="a" href="/vende" class="mt-5 w-full" :variant="plan.highlight ? 'hero' : 'outline'">Elegir plan</Button>
            </div>
        </div>

        <h2 class="mt-8 text-xl font-black text-secondary">Pagos</h2>
        <div class="mt-4 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div v-if="invoices.length" class="divide-y divide-border">
                <div v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between py-3 text-sm">
                    <span class="text-muted-foreground">{{ invoice.date }}</span>
                    <span class="font-bold text-secondary">{{ formatPrice(invoice.amount) }}</span>
                    <span class="rounded-full bg-accent px-2 py-0.5 text-[10px] font-bold uppercase text-accent-foreground">{{ invoice.status }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">Aún no tienes pagos registrados.</p>
        </div>
    </div>
</template>
