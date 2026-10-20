<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Car as CarIcon, Eye, Trash2 } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

interface Publication {
    id: string;
    slug: string;
    title: string;
    price: number;
    km: number;
    image: string | null;
    status?: string | null;
    status_label?: string | null;
    featured?: boolean;
    url: string;
}

const props = defineProps<{
    cars: { data: Publication[]; meta: { current_page: number; last_page: number; total: number } };
    statuses: { id: string; name: string; label: string }[];
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const updateStatus = (id: string, event: Event) => {
    const statusId = (event.target as HTMLSelectElement).value;
    router.patch(`/panel/publicaciones/${id}/status`, { status_id: statusId }, { preserveScroll: true });
};

const destroy = (id: string) => {
    if (!window.confirm('¿Eliminar esta publicación?')) return;
    router.delete(`/panel/publicaciones/${id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-secondary sm:text-3xl">Publicaciones</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ cars.meta.total }} publicaciones en total.</p>
            </div>
            <Button as="a" href="/vende">Publicar auto</Button>
        </div>

        <div v-if="cars.data.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="car in cars.data" :key="car.id" class="overflow-hidden rounded-2xl border border-border bg-card shadow-soft">
                <Link :href="car.url">
                    <img v-if="car.image" :src="car.image" :alt="car.title" class="aspect-[4/3] w-full object-cover" />
                    <div v-else class="grid aspect-[4/3] place-items-center bg-muted text-muted-foreground"><CarIcon class="h-9 w-9" /></div>
                </Link>
                <div class="p-4">
                    <Link :href="car.url" class="font-bold text-secondary hover:text-primary">{{ car.title }}</Link>
                    <p class="mt-1 text-lg font-black text-primary">{{ formatPrice(car.price) }}</p>
                    <p class="text-xs text-muted-foreground">{{ car.km.toLocaleString('es-MX') }} km</p>

                    <select class="mt-3 h-9 w-full rounded-lg border border-input bg-background px-2 text-xs" :value="statuses.find((s) => s.name === car.status)?.id" @change="updateStatus(car.id, $event)">
                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
                    </select>

                    <div class="mt-3 flex gap-2">
                        <Button as="a" :href="car.url" variant="outline" size="sm" class="flex-1"><Eye class="h-3.5 w-3.5" /> Ver</Button>
                        <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(car.id)"><Trash2 class="h-3.5 w-3.5" /></Button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-6 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <CarIcon class="h-10 w-10 text-muted-foreground" />
            <h2 class="mt-4 text-lg font-bold text-secondary">Aún no tienes publicaciones</h2>
            <Button as="a" href="/vende" class="mt-5">Publicar mi primer auto</Button>
        </div>

        <Pagination :current-page="cars.meta.current_page" :last-page="cars.meta.last_page" base-path="/panel/publicaciones" />
    </div>
</template>
