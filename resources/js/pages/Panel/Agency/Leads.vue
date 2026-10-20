<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

interface Lead {
    id: string;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    message: string | null;
    status: string;
    notes: string | null;
    car: string;
    created_at: string | null;
}

const props = defineProps<{
    agency: { id: string; slug: string; name: string };
    leads: { data: Lead[]; current_page: number; last_page: number };
    statuses: string[];
}>();

const base = `/panel/agencia/${props.agency.slug}`;
const notes = reactive<Record<string, string>>(Object.fromEntries(props.leads.data.map((lead) => [lead.id, lead.notes ?? ''])));

const statusLabels: Record<string, string> = {
    new: 'Nuevo',
    contacted: 'Contactado',
    negotiating: 'Negociando',
    won: 'Ganado',
    lost: 'Perdido',
};

const updateStatus = (lead: Lead, event: Event) => {
    router.patch(`${base}/leads/${lead.id}`, { status: (event.target as HTMLSelectElement).value, notes: notes[lead.id] }, { preserveScroll: true });
};

const saveNotes = (lead: Lead) => {
    router.patch(`${base}/leads/${lead.id}`, { status: lead.status, notes: notes[lead.id] }, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Leads</h1>
        <p class="mt-1 text-sm text-muted-foreground">Contactos interesados en el inventario de {{ agency.name }}.</p>

        <div v-if="leads.data.length" class="mt-6 space-y-4">
            <article v-for="lead in leads.data" :key="lead.id" class="rounded-2xl border border-border bg-card p-5 shadow-soft">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-bold text-secondary">{{ lead.contact_name ?? 'Interesado' }}</p>
                        <p class="text-xs text-muted-foreground">{{ lead.car }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm text-muted-foreground">
                            <span v-if="lead.contact_phone">{{ lead.contact_phone }}</span>
                            <span v-if="lead.contact_email">{{ lead.contact_email }}</span>
                        </div>
                        <p v-if="lead.message" class="mt-2 rounded-lg bg-muted/60 p-3 text-sm text-muted-foreground">{{ lead.message }}</p>
                    </div>
                    <select class="h-9 rounded-lg border border-input bg-background px-2 text-xs" :value="lead.status" @change="updateStatus(lead, $event)">
                        <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] ?? status }}</option>
                    </select>
                </div>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <textarea
                        v-model="notes[lead.id]"
                        rows="2"
                        placeholder="Notas internas…"
                        class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <Button variant="outline" size="sm" @click="saveNotes(lead)">Guardar notas</Button>
                </div>
            </article>
        </div>

        <div v-else class="mt-6 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <Inbox class="h-10 w-10 text-muted-foreground" />
            <h2 class="mt-4 text-lg font-bold text-secondary">Sin leads por ahora</h2>
            <p class="mt-2 text-sm text-muted-foreground">Los contactos de tus autos aparecerán aquí.</p>
        </div>

        <Pagination :current-page="leads.current_page" :last-page="leads.last_page" :base-path="`${base}/leads`" />
    </div>
</template>
