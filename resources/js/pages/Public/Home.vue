<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Landmark, Search, Sparkles } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Button from '@/components/ui/Button.vue';
import HomeSections from '@/components/site/HomeSections.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import FinancialPartnersCarousel from '@/components/site/FinancialPartnersCarousel.vue';
import ExternalCarCard from '@/components/external/ExternalCarCard.vue';
import ExternalSearchModal from '@/components/external/ExternalSearchModal.vue';
import ExternalSearchLimitModal from '@/components/external/ExternalSearchLimitModal.vue';
import ExternalAdModal from '@/components/external/ExternalAdModal.vue';
import SearchTokensCard from '@/components/external/SearchTokensCard.vue';
import SearchRewardAdDialog from '@/components/external/SearchRewardAdDialog.vue';
import { useExternalSearch } from '@/composables/useExternalSearch';
import type { ExternalCar } from '@/types/external';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    featuredCars: CarCardData[];
    externalCars: ExternalCar[];
    brands: { id: string; name: string; slug: string; logo: string | null }[];
    plans: { id: string; key_name: string; name: string; price: number; max_cars: number; highlight: boolean }[];
    financieras: { id: string; slug: string; nombre: string; logo: string | null; color: string | null }[];
    seo: { title: string; description: string; canonical: string };
}>();

const localQuery = ref('');
const financiarOpen = ref(false);
const rewardOpen = ref(false);
const externalSearch = useExternalSearch();
const dialogBrands = computed(() => props.brands.map((brand) => ({ ...brand, models: [] })));

onMounted(() => {
    void externalSearch.refreshStatus();
});

const searchExternal = () => {
    void externalSearch.search(localQuery.value.trim() || 'autos seminuevos en México');
};

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
        <img src="/images/hero-verano.webp" alt="Encuentra tu auto ideal" width="1920" height="1080"
            class="absolute inset-0 -z-20 h-full w-full object-cover" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/40 via-secondary/20 to-secondary/70" />
        <div class="mx-auto flex w-full flex-col items-center px-3 py-12 text-white sm:px-5 sm:py-24 md:py-32 lg:py-40">
            <h1 class="page-title text-shadow-hero-soft animate-fade-in text-center text-white">
                Encuentra tu <span class="text-white">auto ideal</span>
            </h1>

            <form class="mx-auto mt-6 w-full max-w-[656px] animate-slide-up sm:mt-10" @submit.prevent="submit">
                <div
                    class="flex items-center gap-1 rounded-full border border-white/30 bg-white/95 p-0.5 shadow-elegant sm:gap-1.5 sm:p-1.5">
                    <div
                        class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-accent text-primary sm:h-8 sm:w-8">
                        <Search class="h-2.5 w-2.5 sm:h-4 sm:w-4" />
                    </div>
                    <input v-model="localQuery" placeholder="Buscar marca, modelo…"
                        class="min-w-0 flex-1 bg-transparent px-0.5 text-[11px] text-secondary outline-none placeholder:text-muted-foreground sm:px-1.5 sm:text-sm" />
                    <Button type="submit" variant="hero" size="pill"
                        class="h-5 shrink-0 gap-1 px-2 text-[10px] sm:h-7 sm:gap-1 sm:px-3.5 sm:text-[11px]">
                        <Sparkles class="h-3 w-3" />
                        Buscar
                    </Button>
                </div>
                <p class="mt-2 flex items-center justify-center gap-1 text-[11px] text-white/60">
                    <Sparkles class="h-3 w-3" /> Búsqueda inteligente
                </p>
            </form>
        </div>
    </section>

    <!-- 2. Barra de financiamiento + carrusel de financieras -->
    <section class="border-y border-border bg-secondary px-4 py-7 text-secondary-foreground sm:px-6 sm:py-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:gap-5">
                <div class="flex min-w-0 items-center gap-3 lg:shrink-0">
                    <div
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                        <Landmark class="h-5 w-5" />
                    </div>
                    <h2
                        class="whitespace-nowrap text-[15px] font-black leading-tight sm:text-lg lg:text-base xl:text-lg">
                        Financia tu próximo seminuevo ahora
                    </h2>
                </div>
                <div
                    class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-2 text-xs font-semibold text-secondary-foreground/80 lg:flex lg:flex-1 lg:gap-x-6">
                    <span
                        v-for="text in ['+35 bancos y financieras', 'Desde 10% de enganche', 'Trámite 100% digital', 'Aprobación de 24 a 48 hrs']"
                        :key="text" class="flex min-w-0 items-center gap-1.5">
                        <span class="h-1 w-1 shrink-0 rounded-full bg-primary" />
                        <span class="truncate">{{ text }}</span>
                    </span>
                </div>
                <Button variant="hero" class="w-full shrink-0 gap-2 lg:w-auto" @click="financiarOpen = true">
                    Simula tu crédito
                    <ArrowRight class="h-4 w-4" />
                </Button>
            </div>

            <div class="mt-5">
                <p
                    class="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-secondary-foreground/60 sm:text-xs">
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
            <Link href="/comprar" class="hidden text-sm font-semibold text-primary hover:underline sm:block">Ver todos
                los autos</Link>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <CarCard v-for="car in featuredCars" :key="car.id" :car="car" />
        </div>
        <div class="mt-8 flex justify-center sm:hidden">
            <Button as="a" href="/comprar" variant="outline" class="w-full">Ver todos los autos</Button>
        </div>
    </section>

    <!-- 3b. Autos externos y buscador inteligente -->
    <section v-if="props.externalCars.length || externalSearch.enabled.value"
        class="border-y border-border bg-muted/30 px-4 py-12 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">Búsqueda inteligente</p>
                    <h2 class="section-title mt-2 text-secondary">También encontramos autos en otras plataformas</h2>
                </div>
                <Button variant="outline" class="gap-2" @click="searchExternal">
                    Buscar en otras plataformas
                </Button>
            </div>

            <div v-if="props.externalCars.length" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <ExternalCarCard v-for="car in props.externalCars" :key="car.id" :car="car"
                    @open="externalSearch.openExternal" />
            </div>

            <div class="mt-8">
                <SearchTokensCard :status="externalSearch.status.value" :on-search="searchExternal" />
            </div>
        </div>
    </section>

    <!-- 4. Garantía teaser, Publica2Min, Beneficios y Marcas (paridad original) -->
    <HomeSections />

    <!-- 5. Explorar por marca -->
    <section v-if="brands.length" class="border-y border-border bg-muted/40 px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <h2 class="section-title text-secondary">Explora por marca</h2>
            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Link v-for="brand in brands" :key="brand.id" :href="`/comprar/marca/${brand.slug}`"
                    class="flex h-20 items-center justify-center rounded-2xl border border-border bg-card text-sm font-bold text-secondary transition hover:border-primary/40 hover:text-primary">
                    {{ brand.name }}
                </Link>
            </div>
        </div>
    </section>

    <!-- 6. Planes para vender -->
    <section v-if="plans.length" class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <h2 class="section-title text-secondary">Planes para vender tu auto</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            <div v-for="plan in plans.filter(p => !p.key_name.startsWith('search_') && !p.key_name.startsWith('membresia_'))"
                :key="plan.id" class="rounded-3xl border bg-card p-6 shadow-soft"
                :class="plan.highlight ? 'border-primary ring-2 ring-primary/20' : 'border-border'">
                <p class="text-sm font-bold uppercase tracking-wide text-primary">{{ plan.name }}</p>
                <p class="mt-3 text-3xl font-black text-secondary">{{ plan.price === 0 ? 'Gratis' :
                    `$${plan.price.toLocaleString('es-MX')}` }}</p>
                <p class="mt-2 text-sm text-muted-foreground">Hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto'
                    : 'autos' }}</p>
                <Button as="a" href="/vende" class="mt-5 w-full" :variant="plan.highlight ? 'hero' : 'outline'">Publicar
                    ahora</Button>
            </div>
        </div>
    </section>

    <!-- 7. Banner inferior CTA -->
    <section class="bg-secondary px-4 py-16 text-center text-white sm:px-6">
        <h2 class="section-title text-white">¿Tienes un auto para vender?</h2>
        <p class="mx-auto mt-4 max-w-xl text-white/70">Publícalo en minutos, recibe ofertas de compradores reales y
            cobra seguro.</p>
        <Button as="a" href="/vende" size="lg" variant="hero" class="mt-8">Publica gratis
            <ArrowRight class="h-4 w-4" />
        </Button>
    </section>

    <!-- Modal global de financiamiento -->
    <FinanciarAutoDialog :open="financiarOpen" :brands="dialogBrands" @close="financiarOpen = false" />

    <ExternalSearchModal :open="externalSearch.showModal.value" :loading="externalSearch.loading.value"
        :results="externalSearch.results.value" :error="externalSearch.error.value"
        :query="externalSearch.lastQuery.value" @close="externalSearch.closeModal" @open="externalSearch.openExternal"
        @retry="searchExternal" />

    <ExternalSearchLimitModal :open="externalSearch.showLimitModal.value" :status="externalSearch.status.value"
        @close="externalSearch.closeLimitModal" @reward="externalSearch.closeLimitModal(); rewardOpen = true" />

    <ExternalAdModal :open="Boolean(externalSearch.externalAd.value)"
        :car="externalSearch.externalAd.value?.car ?? null"
        :source-url="externalSearch.externalAd.value?.sourceUrl ?? null" @close="externalSearch.closeExternalAd" />

    <SearchRewardAdDialog :open="rewardOpen" @close="rewardOpen = false" @reward="externalSearch.refreshStatus" />
</template>
