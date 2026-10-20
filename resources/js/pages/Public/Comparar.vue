<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Scale, X } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import { useCompareStore } from '@/stores/compare';

defineOptions({ layout: PublicLayout });

interface CompareCar {
    id: string;
    slug: string;
    title: string;
    year: number;
    km: number;
    price: number;
    image: string | null;
    transmission: string | null;
    fuel: string | null;
    body: string | null;
    color: string | null;
    city: string | null;
    url: string;
    agency: { name: string } | null;
    owner_name: string | null;
}

const props = defineProps<{
    cars: CompareCar[];
    seo: { title: string; description: string; canonical: string };
}>();

const compare = useCompareStore();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const remove = (id: string) => {
    compare.remove(id);
    const remaining = compare.ids;
    router.get('/comparar', remaining.length ? { ids: remaining.join(',') } : {}, { preserveState: true, replace: true });
};

const clear = () => {
    compare.clear();
    router.get('/comparar', {}, { preserveState: true, replace: true });
};

const rows = [
    { label: 'Precio', key: 'price' },
    { label: 'Año', key: 'year' },
    { label: 'Kilometraje', key: 'km' },
    { label: 'Transmisión', key: 'transmission' },
    { label: 'Combustible', key: 'fuel' },
    { label: 'Carrocería', key: 'body' },
    { label: 'Color', key: 'color' },
    { label: 'Ciudad', key: 'city' },
] as const;

const valueFor = (car: CompareCar, key: (typeof rows)[number]['key']) => {
    if (key === 'price') return formatPrice(car.price);
    if (key === 'km') return `${car.km.toLocaleString('es-MX')} km`;
    return (car[key] as string | number | null) ?? '—';
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="bg-gradient-hero px-4 py-10 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <h1 class="page-title text-secondary">Comparar autos</h1>
            <p class="page-subtitle mt-3 text-muted-foreground">Hasta 3 autos lado a lado.</p>
        </div>
    </section>

    <section v-if="!cars.length" class="mx-auto grid max-w-4xl place-items-center px-4 py-20 text-center sm:px-6">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-accent text-accent-foreground">
            <Scale class="h-6 w-6" />
        </span>
        <h2 class="mt-6 text-xl font-black text-secondary">No hay autos para comparar</h2>
        <p class="mt-2 text-sm text-muted-foreground">Agrega autos desde el catálogo con el botón “Comparar”.</p>
        <Button as="a" href="/comprar" class="mt-6">Explorar catálogo</Button>
    </section>

    <section v-else class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="flex justify-end">
            <Button variant="ghost" size="sm" @click="clear">Limpiar comparador</Button>
        </div>

        <div class="mt-4 overflow-x-auto rounded-3xl border border-border bg-card shadow-soft">
            <table class="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="w-40 border-b border-border p-4 text-left text-xs uppercase tracking-wide text-muted-foreground">Auto</th>
                        <th v-for="car in cars" :key="car.id" class="min-w-56 border-b border-l border-border p-4 align-top">
                            <div class="relative">
                                <button class="absolute right-0 top-0 grid h-7 w-7 place-items-center rounded-full text-muted-foreground hover:bg-accent hover:text-destructive" aria-label="Quitar" @click="remove(car.id)">
                                    <X class="h-4 w-4" />
                                </button>
                                <Link :href="car.url">
                                    <img v-if="car.image" :src="car.image" :alt="car.title" class="h-32 w-full rounded-xl object-cover" />
                                    <div v-else class="grid h-32 place-items-center rounded-xl bg-muted text-muted-foreground">Sin imagen</div>
                                    <p class="mt-3 text-left font-bold text-secondary">{{ car.title }}</p>
                                </Link>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.key" class="odd:bg-muted/30">
                        <th class="p-4 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ row.label }}</th>
                        <td v-for="car in cars" :key="car.id" class="border-l border-border p-4 font-semibold text-secondary">
                            {{ valueFor(car, row.key) }}
                        </td>
                    </tr>
                    <tr class="odd:bg-muted/30">
                        <th class="p-4 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Vendedor</th>
                        <td v-for="car in cars" :key="car.id" class="border-l border-border p-4 text-muted-foreground">
                            {{ car.agency?.name ?? car.owner_name ?? 'Wecar' }}
                        </td>
                    </tr>
                    <tr>
                        <th class="p-4" />
                        <td v-for="car in cars" :key="car.id" class="border-l border-border p-4">
                            <Button as="a" :href="car.url" class="w-full">Ver auto</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
