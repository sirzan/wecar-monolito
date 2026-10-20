<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: AdminLayout });

interface LeadItem {
    id: string;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    message: string | null;
    status: string;
    notes: string | null;
    car: string;
    agency: string | null;
    created_at: string | null;
}

const props = defineProps<{
    leads: { data: LeadItem[]; current_page: number; last_page: number; total: number };
    filters: { status?: string };
    statuses: string[];
}>();

const notes = reactive<Record<string, string>>(Object.fromEntries(props.leads.data.map((lead) => [lead.id, lead.notes ?? ''])));

const update = (lead: LeadItem, status?: string) => {
    router.patch(`/admin/leads/${lead.id}`, { status: status ?? lead.status, notes: notes[lead.id] }, { preserveScroll: true });
};

const filterStatus = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    router.get('/admin/leads', value ? { status: value } : {}, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1200px]">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Leads</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ leads.total }} contactos recibidos.</p>
            </div>
            <select class="h-10 rounded-xl border border-input bg-background px-3 text-sm" :value="filters.status ?? ''" @change="filterStatus">
                <option value="">Todos</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="mt-6 space-y-4">
            <article v-for="lead in leads.data" :key="lead.id" class="rounded-2xl border border-border bg-card/50 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-medium">{{ lead.contact_name ?? 'Interesado' }}</p>
                        <p class="text-xs text-muted-foreground">{{ lead.car }} <span v-if="lead.agency">· {{ lead.agency }}</span></p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ lead.contact_phone }} {{ lead.contact_email }}</p>
                        <p v-if="lead.message" class="mt-2 rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">{{ lead.message }}</p>
                    </div>
                    <select class="h-9 rounded-lg border border-input bg-background px-2 text-xs" :value="lead.status" @change="update(lead, ($event.target as HTMLSelectElement).value)">
                        <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <textarea v-model="notes[lead.id]" rows="2" placeholder="Notas internas…" class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                    <Button variant="outline" size="sm" @click="update(lead)">Guardar notas</Button>
                </div>
            </article>
            <div v-if="!leads.data.length" class="rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center text-sm text-muted-foreground">Sin leads.</div>
        </div>

        <Pagination :current-page="leads.current_page" :last-page="leads.last_page" base-path="/admin/leads" :base-query="filters" />
    </div>
</template>
