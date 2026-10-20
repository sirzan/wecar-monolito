<script setup lang="ts">
import { MapPin, Phone, Globe } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Pagination from '@/components/site/Pagination.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    agency: {
        id: string;
        slug: string;
        name: string;
        logo: string | null;
        description: string | null;
        city: string | null;
        phone: string | null;
        whatsapp: string | null;
        address: string | null;
    };
    cars: { data: CarCardData[]; meta: { current_page: number; last_page: number } };
    seo: { title: string; description: string; canonical: string; image: string | null };
}>();
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :image="seo.image" />

    <section class="bg-gradient-hero px-4 py-12 sm:px-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 sm:flex-row sm:items-center">
            <img v-if="agency.logo" :src="agency.logo" :alt="agency.name" class="h-20 w-20 rounded-2xl border border-border object-contain p-2" />
            <div>
                <h1 class="text-3xl font-black text-secondary sm:text-4xl">{{ agency.name }}</h1>
                <p v-if="agency.description" class="mt-2 max-w-2xl text-sm text-muted-foreground">{{ agency.description }}</p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <span v-if="agency.city" class="inline-flex items-center gap-2"><MapPin class="h-4 w-4 text-primary" /> {{ agency.city }}</span>
                    <span v-if="agency.phone" class="inline-flex items-center gap-2"><Phone class="h-4 w-4 text-primary" /> {{ agency.phone }}</span>
                    <span class="inline-flex items-center gap-2"><Globe class="h-4 w-4 text-primary" /> Agencia certificada</span>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <h2 class="section-title text-secondary">Inventario</h2>

        <div v-if="cars.data.length" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <CarCard v-for="car in cars.data" :key="car.id" :car="car" />
        </div>
        <div v-else class="mt-8 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <p class="text-sm text-muted-foreground">Esta agencia aún no tiene autos publicados.</p>
        </div>

        <Pagination :current-page="cars.meta.current_page" :last-page="cars.meta.last_page" :base-path="`/agencia/${agency.slug}`" />
    </section>
</template>
