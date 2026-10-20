<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';

defineOptions({ layout: AdminLayout });

interface ReportItem {
    id: string;
    reason: string;
    details: string | null;
    status: string;
    car: string;
    car_slug: string | null;
    reporter: string;
    created_at: string | null;
}

const props = defineProps<{
    reports: { data: ReportItem[]; current_page: number; last_page: number; total: number };
    filters: { status?: string };
    statuses: string[];
}>();

const update = (report: ReportItem, event: Event) => {
    router.patch(`/admin/reportes/${report.id}`, { status: (event.target as HTMLSelectElement).value }, { preserveScroll: true });
};

const filterStatus = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    router.get('/admin/reportes', value ? { status: value } : {}, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1200px]">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Reportes</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ reports.total }} reportes de usuarios.</p>
            </div>
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.status ?? ''" @change="filterStatus">
                <option value="">Todos</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr><th class="p-4">Auto</th><th class="p-4">Motivo</th><th class="p-4">Reportado por</th><th class="p-4">Fecha</th><th class="p-4">Estado</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="report in reports.data" :key="report.id">
                        <td class="p-4">
                            <Link v-if="report.car_slug" :href="`/auto/${report.car_slug}`" class="font-medium hover:text-primary">{{ report.car }}</Link>
                            <span v-else class="font-medium">{{ report.car }}</span>
                        </td>
                        <td class="p-4">
                            <p>{{ report.reason }}</p>
                            <p v-if="report.details" class="text-xs text-muted-foreground">{{ report.details }}</p>
                        </td>
                        <td class="p-4 text-muted-foreground">{{ report.reporter }}</td>
                        <td class="p-4 text-xs text-muted-foreground">{{ report.created_at ? new Date(report.created_at).toLocaleDateString('es-MX') : '—' }}</td>
                        <td class="p-4">
                            <select class="h-8 rounded-lg border border-input bg-background px-2 text-xs" :value="report.status" @change="update(report, $event)">
                                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                            </select>
                        </td>
                    </tr>
                    <tr v-if="!reports.data.length"><td colspan="5" class="p-8 text-center text-muted-foreground">Sin reportes.</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="reports.current_page" :last-page="reports.last_page" base-path="/admin/reportes" :base-query="filters" />
    </div>
</template>
