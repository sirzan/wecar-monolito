<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: AdminLayout });

interface AdminAgency {
    id: string;
    slug: string;
    name: string;
    owner: string | null;
    owner_email: string | null;
    city: string | null;
    plan: string;
    plan_expires_at: string | null;
    max_cars: number;
    cars_count: number;
    members_count: number;
    is_active: boolean;
    created_at: string | null;
}

const props = defineProps<{
    agencies: { data: AdminAgency[]; current_page: number; last_page: number; total: number };
    filters: Record<string, string>;
}>();

const q = ref(props.filters.q ?? '');
const active = ref(props.filters.active ?? '');

const state = reactive<Record<string, { plan: string; max_cars: number; plan_expires_at: string; is_active: boolean }>>(
    Object.fromEntries(
        props.agencies.data.map((agency) => [
            agency.id,
            {
                plan: agency.plan,
                max_cars: agency.max_cars,
                plan_expires_at: agency.plan_expires_at ? agency.plan_expires_at.slice(0, 10) : '',
                is_active: agency.is_active,
            },
        ]),
    ),
);

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (q.value) params.q = q.value;
    if (active.value) params.active = active.value;
    router.get('/admin/agencias', params, { preserveState: true, preserveScroll: true, replace: true });
};

const save = (agency: AdminAgency) => {
    router.patch(`/admin/agencias/${agency.id}`, state[agency.id], { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Agencias</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ agencies.total }} agencias registradas.</p>

        <div class="mt-6 grid gap-3 rounded-2xl border border-border bg-card/50 p-4 md:grid-cols-[1fr_180px_auto]">
            <Input v-model="q" placeholder="Buscar agencia…" @keyup.enter="applyFilters" />
            <select v-model="active" class="h-11 rounded-xl border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Todas</option>
                <option value="1">Activas</option>
                <option value="0">Inactivas</option>
            </select>
            <Button @click="applyFilters">Filtrar</Button>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[1000px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="p-4">Agencia</th>
                        <th class="p-4">Dueño</th>
                        <th class="p-4">Inventario</th>
                        <th class="p-4">Plan</th>
                        <th class="p-4">Cupos</th>
                        <th class="p-4">Vigencia</th>
                        <th class="p-4">Activa</th>
                        <th class="p-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="agency in agencies.data" :key="agency.id">
                        <td class="p-4">
                            <p class="font-medium">{{ agency.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ agency.slug }} · {{ agency.city ?? 'Sin ciudad' }}</p>
                        </td>
                        <td class="p-4">
                            <p>{{ agency.owner ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ agency.owner_email }}</p>
                        </td>
                        <td class="p-4 text-xs text-muted-foreground">{{ agency.cars_count }} autos · {{ agency.members_count }} miembros</td>
                        <td class="p-4">
                            <input v-model="state[agency.id].plan" class="h-8 w-28 rounded-lg border border-input bg-background px-2 text-xs" />
                        </td>
                        <td class="p-4">
                            <input v-model.number="state[agency.id].max_cars" type="number" min="1" class="h-8 w-20 rounded-lg border border-input bg-background px-2 text-xs" />
                        </td>
                        <td class="p-4">
                            <input v-model="state[agency.id].plan_expires_at" type="date" class="h-8 rounded-lg border border-input bg-background px-2 text-xs" />
                        </td>
                        <td class="p-4">
                            <input v-model="state[agency.id].is_active" type="checkbox" />
                        </td>
                        <td class="p-4 text-right">
                            <Button size="sm" @click="save(agency)">Guardar</Button>
                        </td>
                    </tr>
                    <tr v-if="!agencies.data.length">
                        <td colspan="8" class="p-8 text-center text-muted-foreground">Sin resultados.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="agencies.current_page" :last-page="agencies.last_page" base-path="/admin/agencias" :base-query="filters" />
    </div>
</template>
