<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';

defineOptions({ layout: AdminLayout });

interface PaymentItem {
    id: string;
    provider: string;
    reference: string | null;
    amount: number;
    currency: string;
    status: string;
    paid_at: string | null;
    created_at: string | null;
    user: string | null;
    email: string | null;
    plan: string | null;
    kind: string | null;
    car: string | null;
}

const props = defineProps<{
    payments: { data: PaymentItem[]; current_page: number; last_page: number; total: number };
    filters: Record<string, string>;
    providers: string[];
    statuses: string[];
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(value);

const filter = (key: string, value: string) => {
    router.get('/admin/pagos', { ...props.filters, [key]: value }, { preserveState: true, replace: true });
};

const update = (payment: PaymentItem, status: string) => {
    router.patch(`/admin/pagos/${payment.id}`, { status }, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Pagos</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ payments.total }} pagos registrados.</p>

        <div class="mt-6 flex flex-wrap gap-3 rounded-2xl border border-border bg-card/50 p-4">
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.provider ?? ''" @change="filter('provider', ($event.target as HTMLSelectElement).value)">
                <option value="">Todos los proveedores</option>
                <option v-for="provider in providers" :key="provider" :value="provider">{{ provider }}</option>
            </select>
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.status ?? ''" @change="filter('status', ($event.target as HTMLSelectElement).value)">
                <option value="">Todos los estatus</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr><th class="p-4">Pago</th><th class="p-4">Usuario</th><th class="p-4">Tipo</th><th class="p-4">Plan / Auto</th><th class="p-4">Monto</th><th class="p-4">Estatus</th><th class="p-4">Fecha</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="payment in payments.data" :key="payment.id">
                        <td class="p-4">
                            <p class="font-medium capitalize">{{ payment.provider }}</p>
                            <p class="text-xs text-muted-foreground">{{ payment.reference ?? payment.id }}</p>
                        </td>
                        <td class="p-4">
                            <p>{{ payment.user ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ payment.email }}</p>
                        </td>
                        <td class="p-4 text-muted-foreground">
                            <p class="text-[10px] font-bold uppercase text-muted-foreground">{{ payment.kind ?? '—' }}</p>
                            <p>{{ payment.plan ?? '—' }}</p>
                            <p class="text-xs">{{ payment.car ?? '' }}</p>
                        </td>
                        <td class="p-4 font-bold tabular-nums">{{ formatPrice(payment.amount) }}</td>
                        <td class="p-4">
                            <select class="h-8 rounded-lg border border-input bg-background px-2 text-xs" :value="payment.status" @change="update(payment, ($event.target as HTMLSelectElement).value)">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </td>
                        <td class="p-4 text-xs text-muted-foreground">
                            {{ payment.created_at ? new Date(payment.created_at).toLocaleDateString('es-MX') : '—' }}
                        </td>
                    </tr>
                    <tr v-if="!payments.data.length"><td colspan="6" class="p-8 text-center text-muted-foreground">Sin pagos.</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="payments.current_page" :last-page="payments.last_page" base-path="/admin/pagos" :base-query="filters" />
    </div>
</template>
