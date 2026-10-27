<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Landmark, Search, Sparkles } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Button from '@/components/ui/Button.vue';
import HomeSections from '@/components/site/HomeSections.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import FinancialPartnersCarousel from '@/components/site/FinancialPartnersCarousel.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    featuredCars: CarCardData[];
    financieras: { id: string; slug: string; nombre: string; logo: string | null; color: string | null }[];
    plans: {
        id: string;
        key_name: string;
        name: string;
        price: number | string;
        duration: string | null;
        max_cars: number;
        highlight: boolean;
    }[];
    seo: { title: string; description: string; canonical: string };
}>();

const localQuery = ref('');
const financiarOpen = ref(false);

const submit = () => {
    router.get('/comprar', localQuery.value ? { q: localQuery.value } : {}, { preserveScroll: true });
};

const jsonLd = [
    {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'Wecar',
        url: props.seo.canonical,
        logo: '/images/wecar-logo.png',
        sameAs: [],
    },
    {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'Wecar',
        url: props.seo.canonical,
        potentialAction: {
            '@type': 'SearchAction',
            target: `${props.seo.canonical}comprar?q={search_term_string}`,
            'query-input': 'required name=search_term_string',
        },
    },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <component :is="'script'" type="application/ld+json" v-text="JSON.stringify(jsonLd)" />

    <!-- 1. Hero con hero-verano.webp y buscador compacto -->
    <section class="relative isolate overflow-hidden">
        <img
            src="/images/hero-verano.webp"
            alt="Encuentra tu auto ideal"
            width="1920"
            height="1080"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/40 via-secondary/20 to-secondary/70" />
        <div class="mx-auto flex w-full flex-col items-center px-3 py-12 text-white sm:px-5 sm:py-24 md:py-32 lg:py-40">
            <h1 class="hero-title page-title text-shadow-hero-soft animate-fade-in text-center text-white">
                Encuentra tu <span class="text-white">auto ideal</span>
            </h1>

            <form class="mx-auto mt-6 w-full max-w-[656px] animate-slide-up sm:mt-10" @submit.prevent="submit">
                <div class="flex items-center gap-1 rounded-full border border-white/30 bg-white/95 p-0.5 shadow-elegant sm:gap-1.5 sm:p-1.5">
                    <div class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-accent text-primary sm:h-8 sm:w-8">
                        <Search class="h-2.5 w-2.5 sm:h-4 sm:w-4" />
                    </div>
                    <input
                        v-model="localQuery"
                        placeholder="Buscar marca, modelo…"
                        class="min-w-0 flex-1 bg-transparent px-0.5 text-[10px] text-secondary outline-none placeholder:text-[10px] placeholder:text-muted-foreground sm:px-1.5 sm:text-xs sm:placeholder:text-xs"
                    />
                    <Button type="submit" variant="hero" size="pill" class="h-5 shrink-0 gap-1 px-2 text-[9px] sm:h-7 sm:gap-1 sm:px-3.5 sm:text-[10px]">
                        <Sparkles class="h-3 w-3" />
                        Buscar
                    </Button>
                </div>
            </form>
        </div>
    </section>

    <!-- 2. Barra de financiamiento + carrusel de financieras -->
    <section class="border-y border-border bg-secondary px-4 py-7 text-secondary-foreground sm:px-6 sm:py-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:gap-5">
                <div class="flex min-w-0 items-center gap-3 lg:shrink-0">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                        <Landmark class="h-5 w-5" />
                    </div>
                    <h2 class="whitespace-nowrap text-[15px] font-black leading-tight sm:text-lg lg:text-base xl:text-lg">
                        Financia tu próximo seminuevo ahora
                    </h2>
                </div>
                <div class="grid min-w-0 grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-3 text-xs font-semibold text-secondary-foreground/80 lg:flex-1 lg:gap-x-8">
                    <span v-for="text in ['+35 bancos y financieras', 'Desde 10% de enganche', 'Trámite 100% digital', 'Aprobación de 24 a 48 hrs']" :key="text" class="flex min-w-0 items-start gap-2">
                        <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary" />
                        <span class="leading-snug">{{ text }}</span>
                    </span>
                </div>
                <Button variant="hero" class="w-full shrink-0 gap-2 lg:w-auto" @click="financiarOpen = true">
                    Simula tu crédito <ArrowRight class="h-4 w-4" />
                </Button>
            </div>

            <div class="mt-5">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-secondary-foreground/60 sm:text-xs">
                    Aliados bancarios y financieros
                </p>
                <FinancialPartnersCarousel :inverse="true" />
            </div>
        </div>
    </section>

    <!-- 3. Catálogo destacado -->
    <section v-if="featuredCars.length" class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Catálogo</p>
                <h2 class="section-title mt-2 text-secondary">Los más buscados, al mejor precio</h2>
            </div>
            <Link href="/comprar" class="hidden text-sm font-semibold text-primary hover:underline sm:block">Ver todos los autos</Link>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <CarCard v-for="car in featuredCars" :key="car.id" :car="car" />
        </div>
        <div class="mt-8 flex justify-center sm:hidden">
            <Button as="a" href="/comprar" variant="outline" class="w-full">Ver todos los autos</Button>
        </div>
    </section>

    <!-- 4. Garantía, beneficios, publica y planes, aliados -->
    <HomeSections :plans="plans" />

    <!-- Modal global de financiamiento -->
    <FinanciarAutoDialog :open="financiarOpen" @close="financiarOpen = false" />

</template>

<style scoped>
@media (max-width: 639px) {
    .hero-title {
        font-size: clamp(1.25rem, 7vw, 1.875rem);
        white-space: nowrap;
    }
}
</style>
