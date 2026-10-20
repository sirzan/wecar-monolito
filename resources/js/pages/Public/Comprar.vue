<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { computed, onMounted, ref, watch } from 'vue';
import { Globe, LoaderCircle, Search, SlidersHorizontal, Sparkles, Tag, X } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
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
    filters: Record<string, string>;
    cars: { data: CarCardData[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };
    externalCars: ExternalCar[];
    options: {
        brands: { id: string; name: string; slug: string; count: number }[];
        cities: { value: string; count: number }[];
        bodies: { value: string; count: number }[];
        transmissions: { value: string; count: number }[];
        fuels: { value: string; count: number }[];
        price: { min: number; max: number };
        year: { min: number; max: number };
    };
    seo: { title: string; description: string; canonical: string; noindex?: boolean };
}>();

const form = ref({
    q: props.filters.q ?? '',
    brand: props.filters.brand ?? '',
    city: props.filters.city ?? '',
    body: props.filters.body ?? '',
    transmission: props.filters.transmission ?? '',
    fuel: props.filters.fuel ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
    min_year: props.filters.min_year ?? '',
    max_year: props.filters.max_year ?? '',
    sort: props.filters.sort ?? 'recent',
});

const showFilters = ref(false);
const aiLoading = ref(false);
const page = usePage<{ externalSearch: { enabled: boolean } }>();
const externalSearch = useExternalSearch();
const rewardOpen = ref(false);
let debounce: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    void externalSearch.refreshStatus();
});

const activeFiltersCount = computed(
    () => Object.entries(form.value).filter(([key, value]) => key !== 'sort' && value).length,
);

const apply = (immediate = false) => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(
        () => {
            const params: Record<string, string> = {};
            Object.entries(form.value).forEach(([key, value]) => {
                if (value) params[key] = value;
            });
            router.get('/comprar', params, { preserveState: true, preserveScroll: true, replace: true });
        },
        immediate ? 0 : 350,
    );
};

watch(() => form.value.q, () => apply());
watch(
    () => [form.value.brand, form.value.city, form.value.body, form.value.transmission, form.value.fuel, form.value.min_price, form.value.max_price, form.value.min_year, form.value.max_year, form.value.sort],
    () => apply(true),
);


const applyAiFilters = async () => {
    if (!form.value.q.trim() || aiLoading.value) return;

    aiLoading.value = true;

    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch('/api/ai-search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ q: form.value.q }),
        });

        if (!response.ok) throw new Error('No se pudo interpretar la búsqueda.');

        const data = await response.json();
        const filters = data.filters ?? {};

        form.value.brand = filters.brand ?? '';
        form.value.body = filters.body ?? '';
        form.value.transmission = filters.transmission ?? '';
        form.value.fuel = filters.fuel ?? '';
        form.value.min_price = filters.min_price ? String(filters.min_price) : '';
        form.value.max_price = filters.max_price ? String(filters.max_price) : '';
        form.value.min_year = filters.min_year ? String(filters.min_year) : '';
        form.value.max_year = filters.max_year ? String(filters.max_year) : '';
        form.value.q = filters.q ?? form.value.q;

        apply(true);
        toast.success(data.source === 'ai' ? 'Filtros aplicados con IA.' : 'Búsqueda aplicada.');
    } catch {
        toast.error('No pudimos aplicar la búsqueda inteligente.');
    } finally {
        aiLoading.value = false;
    }
};


const searchExternal = () => {
    void externalSearch.search(form.value.q.trim() || 'autos seminuevos en México');
};

const clear = () => {
    form.value = { q: '', brand: '', city: '', body: '', transmission: '', fuel: '', min_price: '', max_price: '', min_year: '', max_year: '', sort: 'recent' };
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex ?? false" />

    <section class="bg-gradient-hero px-4 pb-3 pt-6 sm:px-6 sm:pb-4 sm:pt-8">
        <div class="mx-auto flex max-w-7xl items-start justify-between gap-3">
            <div>
                <h1 class="section-title whitespace-nowrap text-secondary">Catálogo de autos</h1>
                <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                    Describe tu auto ideal y nosotros encontramos las mejores opciones para ti.
                </p>
            </div>
            <Link
                href="/vende"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary px-3 py-1.5 text-[10px] font-black tracking-wider text-primary-foreground shadow-soft transition-transform hover:scale-105 sm:text-xs"
            >
                <Tag class="h-3.5 w-3.5" /> Publica Gratis
            </Link>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pt-2 sm:px-6">
        <form
            class="mt-2 flex flex-col gap-2 rounded-3xl border border-border bg-card p-1.5 shadow-soft sm:mt-5 sm:flex-row sm:items-center sm:rounded-full sm:p-2"
            @submit.prevent="applyAiFilters"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2 px-3">
                <Search class="h-5 w-5 shrink-0 text-primary" />
                <input
                    v-model="form.q"
                    placeholder="Quiero una SUV familiar, automática, de menos de $350,000 y bajo kilometraje…"
                    class="h-10 min-w-0 flex-1 bg-transparent text-xs text-secondary outline-none placeholder:text-muted-foreground sm:h-12 sm:text-base"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button type="submit" variant="hero" size="pill" :disabled="aiLoading || !form.q.trim()" class="h-10 shrink-0 gap-1 px-4 text-xs sm:h-12 sm:gap-1.5 sm:px-6 sm:text-sm">
                    <LoaderCircle v-if="aiLoading" class="h-4 w-4 animate-spin" />
                    Buscar con IA <Sparkles class="h-4 w-4" />
                </Button>
                <Button
                    v-if="externalSearch.enabled.value || page.props.externalSearch?.enabled"
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="externalSearch.loading.value"
                    class="h-10 shrink-0 text-xs sm:h-12"
                    @click="searchExternal"
                >
                    <LoaderCircle v-if="externalSearch.loading.value" class="h-4 w-4 animate-spin" />
                    <Globe v-else class="h-4 w-4" />
                    Otras plataformas
                </Button>
                <Button type="button" variant="outline" size="pill" class="h-10 shrink-0 text-xs lg:hidden" @click="showFilters = !showFilters">
                    <SlidersHorizontal class="h-4 w-4" /> Filtros
                </Button>
                <select v-model="form.sort" class="h-10 rounded-lg border border-input bg-background px-3 text-sm">
                    <option value="recent">Más recientes</option>
                    <option value="price-asc">Precio: menor a mayor</option>
                    <option value="price-desc">Precio: mayor a menor</option>
                    <option value="year-desc">Año: más nuevo</option>
                    <option value="km-asc">Menos kilómetros</option>
                </select>
            </div>
        </form>
    </section>

    <section class="mx-auto flex max-w-7xl gap-8 px-4 py-8 sm:px-6">
        <aside :class="['w-72 shrink-0', showFilters ? 'block' : 'hidden lg:block']">
            <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-secondary">Filtros</h2>
                    <span v-if="activeFiltersCount" class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-bold text-secondary-foreground">{{ activeFiltersCount }}</span>
                </div>
                <button v-if="activeFiltersCount" class="text-xs font-semibold text-primary hover:underline" @click="clear">Limpiar</button>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Marca</p>
                <select v-model="form.brand" class="mt-2 h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                    <option value="">Todas</option>
                    <option v-for="brand in options.brands" :key="brand.id" :value="brand.slug">{{ brand.name }} ({{ brand.count }})</option>
                </select>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Ciudad</p>
                <select v-model="form.city" class="mt-2 h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                    <option value="">Todas</option>
                    <option v-for="city in options.cities" :key="city.value" :value="city.value">{{ city.value }} ({{ city.count }})</option>
                </select>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Carrocería</p>
                <div class="mt-2 space-y-1">
                    <label v-for="body in options.bodies" :key="body.value" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input v-model="form.body" type="radio" :value="body.value" class="h-4 w-4" />
                        {{ body.value }} ({{ body.count }})
                    </label>
                    <label class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input v-model="form.body" type="radio" value="" class="h-4 w-4" /> Todas
                    </label>
                </div>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Transmisión</p>
                <select v-model="form.transmission" class="mt-2 h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                    <option value="">Todas</option>
                    <option v-for="item in options.transmissions" :key="item.value" :value="item.value">{{ item.value }} ({{ item.count }})</option>
                </select>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Combustible</p>
                <select v-model="form.fuel" class="mt-2 h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                    <option value="">Todos</option>
                    <option v-for="item in options.fuels" :key="item.value" :value="item.value">{{ item.value }} ({{ item.count }})</option>
                </select>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Precio (MXN)</p>
                <div class="mt-2 flex items-center gap-2">
                    <Input v-model="form.min_price" type="number" placeholder="Mín" class="h-10" />
                    <span class="text-muted-foreground">–</span>
                    <Input v-model="form.max_price" type="number" placeholder="Máx" class="h-10" />
                </div>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Año</p>
                <div class="mt-2 flex items-center gap-2">
                    <Input v-model="form.min_year" type="number" placeholder="Desde" class="h-10" />
                    <span class="text-muted-foreground">–</span>
                    <Input v-model="form.max_year" type="number" placeholder="Hasta" class="h-10" />
                </div>
            </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{ cars.meta.total.toLocaleString('es-MX') }} {{ cars.meta.total === 1 ? 'auto encontrado' : 'autos encontrados' }}
                </p>
            </div>
            <div v-if="cars.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <CarCard v-for="car in cars.data" :key="car.id" :car="car" />
            </div>

            <div v-else class="grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
                <X class="h-10 w-10 text-muted-foreground" />
                <h2 class="mt-4 text-lg font-bold text-secondary">Sin resultados</h2>
                <p class="mt-2 max-w-md text-sm text-muted-foreground">
                    Prueba ajustando los filtros o ampliando el rango de precio y año.
                </p>
                <Button class="mt-5" @click="clear">Limpiar filtros</Button>
            </div>

            <Pagination :current-page="cars.meta.current_page" :last-page="cars.meta.last_page" :base-query="filters" />

            <section v-if="props.externalCars.length || externalSearch.enabled.value" class="mt-12 border-t border-border pt-8">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">Búsqueda inteligente</p>
                        <h2 class="mt-1 text-xl font-black text-secondary">Autos encontrados en otras plataformas</h2>
                    </div>
                    <Button variant="outline" size="pill" class="gap-2" @click="searchExternal">
                        <Globe class="h-4 w-4" /> Buscar más autos
                    </Button>
                </div>

                <div v-if="props.externalCars.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <ExternalCarCard v-for="car in props.externalCars" :key="car.id" :car="car" @open="externalSearch.openExternal" />
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Aún no hay autos externos en caché. Realiza una búsqueda para encontrarlos.</p>

                <div class="mt-6">
                    <SearchTokensCard :status="externalSearch.status.value" :on-search="searchExternal" />
                </div>
            </section>
        </div>
    </section>

    <ExternalSearchModal
        :open="externalSearch.showModal.value"
        :loading="externalSearch.loading.value"
        :results="externalSearch.results.value"
        :error="externalSearch.error.value"
        :query="externalSearch.lastQuery.value"
        @close="externalSearch.closeModal"
        @open="externalSearch.openExternal"
        @retry="searchExternal"
    />

    <ExternalSearchLimitModal
        :open="externalSearch.showLimitModal.value"
        :status="externalSearch.status.value"
        @close="externalSearch.closeLimitModal"
        @reward="externalSearch.closeLimitModal(); rewardOpen = true"
    />

    <ExternalAdModal
        :open="Boolean(externalSearch.externalAd.value)"
        :car="externalSearch.externalAd.value?.car ?? null"
        :source-url="externalSearch.externalAd.value?.sourceUrl ?? null"
        @close="externalSearch.closeExternalAd"
    />

    <SearchRewardAdDialog :open="rewardOpen" @close="rewardOpen = false" @reward="externalSearch.refreshStatus" />
</template>
