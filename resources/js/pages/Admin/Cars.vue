<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ExternalLink, Star, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: AdminLayout });

interface AdminCar {
    id: string;
    slug: string;
    title: string;
    price: number;
    km: number;
    image: string | null;
    status_id: string | null;
    status: string | null;
    featured: boolean;
    is_published: boolean;
    owner: string | null;
    agency: string | null;
    created_at: string | null;
    url: string;
}

const page = usePage<{ auth: { user: { roles: string[] } | null } }>();
const canPublish = computed(() => {
    const roles = page.props.auth.user?.roles ?? [];
    return roles.includes('superadmin') || roles.includes('manager');
});

const props = defineProps<{
    cars: { data: AdminCar[]; current_page: number; last_page: number; total: number };
    filters: Record<string, string>;
    statuses: { id: string; name: string; label: string }[];
    agencies: { id: string; name: string; slug: string }[];
}>();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const featured = ref(props.filters.featured ?? '');
const agencyId = ref(props.filters.agency_id ?? '');

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (q.value) params.q = q.value;
    if (status.value) params.status = status.value;
    if (featured.value) params.featured = featured.value;
    if (agencyId.value) params.agency_id = agencyId.value;
    router.get('/admin/autos', params, { preserveState: true, preserveScroll: true, replace: true });
};

const update = (car: AdminCar, payload: Record<string, string | boolean | number | null>) => {
    router.patch(`/admin/autos/${car.id}`, payload, { preserveScroll: true });
};

const destroy = (car: AdminCar) => {
    if (!window.confirm(`¿Eliminar ${car.title}?`)) return;
    router.delete(`/admin/autos/${car.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Autos</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ cars.total }} autos registrados.</p>
            </div>
            <Button v-if="canPublish" as="a" href="/admin/autos/crear">Publicar auto</Button>
        </div>

        <div class="mt-6 grid gap-3 rounded-2xl border border-border bg-card/50 p-4 md:grid-cols-[1fr_180px_140px_200px_auto]">
            <Input v-model="q" placeholder="Buscar por marca, modelo, vendedor…" @keyup.enter="applyFilters" />
            <select v-model="status" class="h-11 rounded-xl border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Todos los estatus</option>
                <option v-for="item in statuses" :key="item.id" :value="item.name">{{ item.label }}</option>
            </select>
            <select v-model="featured" class="h-11 rounded-xl border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Destacados</option>
                <option value="1">Solo destacados</option>
                <option value="0">No destacados</option>
            </select>
            <select v-model="agencyId" class="h-11 rounded-xl border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Todas las agencias</option>
                <option v-for="agency in agencies" :key="agency.id" :value="agency.id">{{ agency.name }}</option>
            </select>
            <Button @click="applyFilters">Filtrar</Button>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="p-4">Auto</th>
                        <th class="p-4">Precio</th>
                        <th class="p-4">Vendedor</th>
                        <th class="p-4">Estatus</th>
                        <th class="p-4">Destacado</th>
                        <th class="p-4">Publicado</th>
                        <th class="p-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="car in cars.data" :key="car.id">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <img v-if="car.image" :src="car.image" :alt="car.title" class="h-12 w-16 rounded-lg object-cover" />
                                <div>
                                    <p class="font-medium">{{ car.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ car.km.toLocaleString('es-MX') }} km · {{ car.slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 tabular-nums">{{ formatPrice(car.price) }}</td>
                        <td class="p-4">
                            <p>{{ car.owner ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ car.agency ?? 'Particular' }}</p>
                        </td>
                        <td class="p-4">
                            <select class="h-8 rounded-lg border border-input bg-background px-2 text-xs" :value="car.status_id" @change="update(car, { status_id: ($event.target as HTMLSelectElement).value })">
                                <option v-for="item in statuses" :key="item.id" :value="item.id">{{ item.label }}</option>
                            </select>
                        </td>
                        <td class="p-4">
                            <button class="grid h-8 w-8 place-items-center rounded-lg" :class="car.featured ? 'bg-primary/15 text-primary' : 'text-muted-foreground'" @click="update(car, { featured: !car.featured })">
                                <Star class="h-4 w-4" :fill="car.featured ? 'currentColor' : 'none'" />
                            </button>
                        </td>
                        <td class="p-4">
                            <button class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="car.is_published ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'" @click="update(car, { is_published: !car.is_published })">
                                {{ car.is_published ? 'Publicado' : 'Oculto' }}
                            </button>
                        </td>
                        <td class="p-4">
                            <div class="flex justify-end gap-1">
                                <Button as="a" :href="car.url" variant="ghost" size="sm"><ExternalLink class="h-4 w-4" /></Button>
                                <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(car)"><Trash2 class="h-4 w-4" /></Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!cars.data.length">
                        <td colspan="7" class="p-8 text-center text-muted-foreground">Sin resultados.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :current-page="cars.current_page" :last-page="cars.last_page" base-path="/admin/autos" :base-query="filters" />
    </div>
</template>
