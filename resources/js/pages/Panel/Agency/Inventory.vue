<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Car as CarIcon, Eye, Star, Trash2 } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    agency: { id: string; slug: string; name: string; max_cars: number };
    cars: {
        data: { id: string; slug: string; title: string; price: number; km: number; image: string | null; status_id: string | null; status: string | null; featured: boolean; url: string }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    statuses: { id: string; name: string; label: string }[];
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const updateCar = (carId: string, payload: { status_id?: string; featured?: boolean }) => {
    router.patch(`${base}/inventario/${carId}`, payload, { preserveScroll: true });
};

const updateStatus = (carId: string, event: Event) => {
    updateCar(carId, { status_id: (event.target as HTMLSelectElement).value });
};

const toggleFeatured = (carId: string, featured: boolean) => {
    updateCar(carId, { featured: !featured });
};

const destroy = (carId: string) => {
    if (!window.confirm('¿Eliminar este auto del inventario?')) return;
    router.delete(`${base}/inventario/${carId}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
                <h1 class="text-2xl font-black text-secondary sm:text-3xl">Inventario</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ cars.total }} de {{ agency.max_cars }} cupos usados.</p>
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

                    <select class="mt-3 h-9 w-full rounded-lg border border-input bg-background px-2 text-xs" :value="car.status_id" @change="updateStatus(car.id, $event)">
                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
                    </select>

                    <div class="mt-3 flex gap-2">
                        <Button as="a" :href="car.url" variant="outline" size="sm" class="flex-1"><Eye class="h-3.5 w-3.5" /> Ver</Button>
                        <Button variant="ghost" size="sm" :class="car.featured ? 'text-primary' : 'text-muted-foreground'" @click="toggleFeatured(car.id, car.featured)">
                            <Star class="h-3.5 w-3.5" :fill="car.featured ? 'currentColor' : 'none'" />
                        </Button>
                        <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(car.id)"><Trash2 class="h-3.5 w-3.5" /></Button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-6 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <CarIcon class="h-10 w-10 text-muted-foreground" />
            <h2 class="mt-4 text-lg font-bold text-secondary">Inventario vacío</h2>
            <Button as="a" href="/vende" class="mt-5">Publicar primer auto</Button>
        </div>

        <Pagination :current-page="cars.current_page" :last-page="cars.last_page" :base-path="`${base}/inventario`" />
    </div>
</template>
