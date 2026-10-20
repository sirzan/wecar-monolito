<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, FileText, XCircle } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    applications: {
        data: {
            id: string;
            folio: string;
            full_name: string;
            email: string;
            phone: string;
            car: string;
            financiera: string | null;
            score: number | null;
            risk_level: string | null;
            status: string;
            monthly_payment: number;
            created_at: string | null;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Record<string, string>;
    statuses: string[];
    counts: { total: number; review: number; approved: number; rejected: number };
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const filter = (key: string, value: string) => {
    router.get('/admin/creditos', { ...props.filters, [key]: value }, { preserveState: true, replace: true });
};

const statusTone = (status: string) =>
    status === 'aprobado'
        ? 'bg-emerald-500/10 text-emerald-400'
        : status === 'rechazado'
          ? 'bg-rose-500/10 text-rose-400'
          : 'bg-amber-500/10 text-amber-400';
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Créditos</h1>
        <p class="mt-1 text-sm text-muted-foreground">Solicitudes y trámites de financiamiento.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card/50 p-5">
                <CreditCard class="h-5 w-5 text-primary" />
                <p class="mt-3 text-2xl font-semibold">{{ counts.total }}</p>
                <p class="text-xs text-muted-foreground">Total</p>
            </div>
            <div class="rounded-2xl border border-border bg-card/50 p-5">
                <FileText class="h-5 w-5 text-amber-400" />
                <p class="mt-3 text-2xl font-semibold">{{ counts.review }}</p>
                <p class="text-xs text-muted-foreground">En revisión</p>
            </div>
            <div class="rounded-2xl border border-border bg-card/50 p-5">
                <CheckCircle2 class="h-5 w-5 text-emerald-400" />
                <p class="mt-3 text-2xl font-semibold">{{ counts.approved }}</p>
                <p class="text-xs text-muted-foreground">Aprobados</p>
            </div>
            <div class="rounded-2xl border border-border bg-card/50 p-5">
                <XCircle class="h-5 w-5 text-rose-400" />
                <p class="mt-3 text-2xl font-semibold">{{ counts.rejected }}</p>
                <p class="text-xs text-muted-foreground">Rechazados</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3 rounded-2xl border border-border bg-card/50 p-4">
            <input
                :value="filters.q ?? ''"
                placeholder="Buscar folio, nombre o correo…"
                class="h-10 flex-1 rounded-xl border border-input bg-background px-3 text-sm"
                @keyup.enter="filter('q', ($event.target as HTMLInputElement).value)"
            />
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.status ?? ''" @change="filter('status', ($event.target as HTMLSelectElement).value)">
                <option value="">Todos los estatus</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr><th class="p-4">Folio</th><th class="p-4">Cliente</th><th class="p-4">Auto</th><th class="p-4">Financiera</th><th class="p-4">Score</th><th class="p-4">Mensualidad</th><th class="p-4">Estatus</th><th class="p-4 text-right">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="application in applications.data" :key="application.id">
                        <td class="p-4 font-mono text-xs">{{ application.folio }}</td>
                        <td class="p-4">
                            <p class="font-medium">{{ application.full_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ application.email }}</p>
                            <p class="text-xs text-muted-foreground">{{ application.phone }}</p>
                        </td>
                        <td class="p-4 text-muted-foreground">{{ application.car }}</td>
                        <td class="p-4 text-muted-foreground">{{ application.financiera ?? '—' }}</td>
                        <td class="p-4">
                            <span class="font-semibold">{{ application.score ?? '—' }}</span>
                            <span v-if="application.risk_level" class="ml-1 text-xs text-muted-foreground">({{ application.risk_level }})</span>
                        </td>
                        <td class="p-4 font-semibold">{{ formatPrice(application.monthly_payment) }}</td>
                        <td class="p-4">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="statusTone(application.status)">{{ application.status }}</span>
                        </td>
                        <td class="p-4 text-right">
                            <Link :href="`/admin/creditos/${application.id}`" class="text-xs font-semibold text-primary hover:underline">Ver detalle</Link>
                        </td>
                    </tr>
                    <tr v-if="!applications.data.length"><td colspan="8" class="p-8 text-center text-muted-foreground">Sin solicitudes.</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="applications.current_page" :last-page="applications.last_page" base-path="/admin/creditos" :base-query="filters" />
    </div>
</template>
