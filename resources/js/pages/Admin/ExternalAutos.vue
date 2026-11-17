<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, Search, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: AdminLayout });

interface ExternalCar {
    id: string;
    title: string;
    price: number | null;
    year: number | null;
    km: number | null;
    location: string | null;
    imageUrl: string | null;
    sourceUrl: string;
    platformId: string;
    platformName: string;
}

interface ExternalGroup {
    id: string;
    query_text: string;
    platforms: string[];
    updated_at: string | null;
    cars: ExternalCar[];
}

const props = defineProps<{
    groups: { data: ExternalGroup[]; current_page: number; last_page: number; total: number };
    filters: { q?: string; platform?: string };
    platforms: { id: string; name: string }[];
}>();

const q = ref(props.filters.q ?? '');
const platform = ref(props.filters.platform ?? '');
const selectedIds = ref<string[]>([]);

const pageCarIds = computed(() => [
    ...new Set(props.groups.data.flatMap((group) => group.cars.map((car) => car.id))),
]);

const allPageSelected = computed(
    () => pageCarIds.value.length > 0 && pageCarIds.value.every((id) => selectedIds.value.includes(id)),
);

const formatPrice = (value: number | null) =>
    value === null
        ? 'Precio no disponible'
        : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : 'Sin fecha';

const applyFilters = () => {
    const params: Record<string, string> = {};

    if (q.value) params.q = q.value;
    if (platform.value) params.platform = platform.value;

    router.get('/admin/autos-externos', params, { preserveState: true, preserveScroll: true, replace: true });
};

const togglePageSelection = () => {
    if (allPageSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !pageCarIds.value.includes(id));
        return;
    }

    selectedIds.value = [...new Set([...selectedIds.value, ...pageCarIds.value])];
};

const deleteSelected = () => {
    if (!selectedIds.value.length || !window.confirm(`¿Eliminar ${selectedIds.value.length} autos externos?`)) return;

    router.post(
        '/admin/autos-externos/seleccion',
        { ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        },
    );
};

const deleteCar = (car: ExternalCar) => {
    if (!window.confirm(`¿Eliminar ${car.title}?`)) return;

    router.delete(`/admin/autos-externos/${car.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = selectedIds.value.filter((id) => id !== car.id);
        },
    });
};

const deleteGroup = (group: ExternalGroup) => {
    if (!window.confirm(`¿Eliminar todo el grupo "${group.query_text}"?`)) return;

    router.delete(`/admin/autos-externos/grupos/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            const groupIds = group.cars.map((car) => car.id);
            selectedIds.value = selectedIds.value.filter((id) => !groupIds.includes(id));
        },
    });
};
</script>

<template>
    <Head title="Autos externos · Admin" />

    <div class="mx-auto max-w-[1400px]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Búsqueda externa</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Autos externos</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Gestiona los resultados almacenados por búsqueda sin tocar los autos publicados en Wecar.
                </p>
            </div>

            <Button variant="destructive" :disabled="!selectedIds.length" @click="deleteSelected">
                <Trash2 class="h-4 w-4" />
                Eliminar seleccionados<span v-if="selectedIds.length"> ({{ selectedIds.length }})</span>
            </Button>
        </div>

        <div class="mt-6 grid gap-3 rounded-2xl border border-border bg-card/50 p-4 md:grid-cols-[1fr_240px_auto]">
            <Input v-model="q" placeholder="Buscar grupo por texto…" @keyup.enter="applyFilters" />
            <select v-model="platform" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                <option value="">Todas las plataformas</option>
                <option v-for="item in platforms" :key="item.id" :value="item.name">{{ item.name }}</option>
            </select>
            <Button @click="applyFilters">
                <Search class="h-4 w-4" />
                Filtrar
            </Button>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card/50 px-4 py-3">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input :checked="allPageSelected" type="checkbox" class="h-4 w-4 accent-primary" @change="togglePageSelection" />
                Seleccionar autos de esta página
            </label>
            <p class="text-sm text-muted-foreground">{{ groups.total }} grupos activos en el caché.</p>
        </div>

        <div v-if="groups.data.length" class="mt-6 space-y-6">
            <section v-for="group in groups.data" :key="group.id" class="overflow-hidden rounded-2xl border border-border bg-card/50">
                <div class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-foreground">{{ group.query_text }}</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ group.cars.length }} autos · {{ group.platforms.join(', ') || 'Sin plataforma' }} · actualizado {{ formatDate(group.updated_at) }}
                        </p>
                    </div>
                    <Button variant="outline" size="sm" class="text-destructive" @click="deleteGroup(group)">
                        <Trash2 class="h-4 w-4" />
                        Eliminar grupo
                    </Button>
                </div>

                <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="car in group.cars" :key="`${group.id}-${car.id}`" class="relative overflow-hidden rounded-xl border border-border bg-background">
                        <label class="absolute left-3 top-3 z-10 rounded-md bg-background/90 p-1.5 shadow-sm">
                            <input v-model="selectedIds" type="checkbox" :value="car.id" class="h-4 w-4 accent-primary" />
                        </label>
                        <img v-if="car.imageUrl" :src="car.imageUrl" :alt="car.title" class="h-40 w-full object-cover" />
                        <div v-else class="grid h-40 place-items-center bg-muted text-xs text-muted-foreground">Sin imagen</div>
                        <div class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="line-clamp-2 text-sm font-semibold">{{ car.title }}</h3>
                                <span class="shrink-0 rounded-full bg-primary/10 px-2 py-1 text-[10px] font-semibold text-primary">{{ car.platformName }}</span>
                            </div>
                            <p class="text-sm font-bold text-foreground">{{ formatPrice(car.price) }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ car.year ?? 'Año no disponible' }} · {{ car.km !== null ? `${car.km.toLocaleString('es-MX')} km` : 'Kilometraje no disponible' }}
                            </p>
                            <p v-if="car.location" class="truncate text-xs text-muted-foreground">{{ car.location }}</p>
                            <div class="flex items-center justify-between gap-2 pt-2">
                                <a :href="car.sourceUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                    Ver fuente <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-destructive hover:underline" @click="deleteCar(car)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 py-20 text-center text-sm text-muted-foreground">
            No hay grupos de autos externos que coincidan con los filtros.
        </div>

        <Pagination
            :current-page="groups.current_page"
            :last-page="groups.last_page"
            base-path="/admin/autos-externos"
            :base-query="filters"
        />
    </div>
</template>
