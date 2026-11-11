<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { computed, onMounted, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ArrowUpDown, Check, ChevronDown, Globe, LoaderCircle, Search, SlidersHorizontal, Sparkles, Tag, X } from '@lucide/vue';
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
        models: { id: string; name: string; brand_id: string }[];
        versions: { id: string; name: string; model_id: string }[];
        cities: { value: string; count: number }[];
        bodies: { value: string; count: number }[];
        transmissions: { value: string; count: number }[];
        fuels: { value: string; count: number }[];
        colors: { value: string; count: number }[];
        price: { min: number; max: number };
        year: { min: number; max: number };
    };
    seo: { title: string; description: string; canonical: string; noindex?: boolean };
}>();

const form = ref({
    q: props.filters.q ?? '',
    brand: props.filters.brand ?? '',
    model: props.filters.model ?? '',
    version: props.filters.version ?? '',
    city: props.filters.city ?? '',
    body: props.filters.body ?? '',
    transmission: props.filters.transmission ?? '',
    fuel: props.filters.fuel ?? '',
    color: props.filters.color ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
    min_year: props.filters.min_year ?? '',
    max_year: props.filters.max_year ?? '',
    sort: props.filters.sort ?? 'recent',
});

const sortOptions = [
    { value: 'recent', label: 'Más recientes' },
    { value: 'price-asc', label: 'Precio: menor a mayor' },
    { value: 'price-desc', label: 'Precio: mayor a menor' },
    { value: 'year-desc', label: 'Año: más nuevo' },
    { value: 'km-asc', label: 'Menos kilómetros' },
];

const sortOpen = ref(false);
const sortRef = ref<HTMLElement | null>(null);
onClickOutside(sortRef, () => {
    sortOpen.value = false;
});

const sortLabel = computed(
    () => sortOptions.find((option) => option.value === form.value.sort)?.label ?? 'Más recientes',
);

const chooseSort = (value: string) => {
    form.value.sort = value;
    sortOpen.value = false;
};

const openGroups = ref<Record<string, boolean>>({
    brand: true,
    model: true,
    version: true,
    city: false,
    body: false,
    transmission: false,
    fuel: false,
    color: false,
    price: false,
    year: false,
});

const toggleGroup = (key: string) => {
    openGroups.value[key] = !openGroups.value[key];
};

const selectedBrandId = computed(
    () => props.options.brands.find((brand) => brand.slug === form.value.brand)?.id ?? '',
);

const modelOptions = computed(() =>
    selectedBrandId.value
        ? props.options.models.filter((model) => model.brand_id === selectedBrandId.value)
        : [],
);

const versionOptions = computed(() =>
    form.value.model ? props.options.versions.filter((version) => version.model_id === form.value.model) : [],
);

const showFilters = ref(false);
const aiLoading = ref(false);
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
    () => [form.value.brand, form.value.model, form.value.version, form.value.city, form.value.body, form.value.transmission, form.value.fuel, form.value.color, form.value.min_price, form.value.max_price, form.value.min_year, form.value.max_year, form.value.sort],
    () => apply(true),
);

watch(
    () => form.value.brand,
    () => {
        if (form.value.model && !modelOptions.value.some((model) => model.id === form.value.model)) {
            form.value.model = '';
        }

        if (form.value.version && !versionOptions.value.some((version) => version.id === form.value.version)) {
            form.value.version = '';
        }
    },
);

watch(
    () => form.value.model,
    () => {
        if (form.value.version && !versionOptions.value.some((version) => version.id === form.value.version)) {
            form.value.version = '';
        }
    },
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
    form.value = { q: '', brand: '', model: '', version: '', city: '', body: '', transmission: '', fuel: '', color: '', min_price: '', max_price: '', min_year: '', max_year: '', sort: 'recent' };
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex ?? false" />

    <section class="bg-gradient-hero px-4 pb-2 pt-5 sm:px-6 sm:pb-3 sm:pt-6">
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

    <section class="mx-auto flex max-w-7xl gap-8 px-4 py-8 sm:px-6">
        <aside :class="['w-72 shrink-0', showFilters ? 'block' : 'hidden lg:block']">
            <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-secondary">Filtros</h2>
                    <span v-if="activeFiltersCount" class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-bold text-secondary-foreground">{{ activeFiltersCount }}</span>
                </div>
            </div>

            <div class="border-t border-border/60 py-3 first:border-t-0 first:pt-0">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.brand" @click="toggleGroup('brand')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Marca</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.brand ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.brand" class="mt-2">
                    <select v-model="form.brand" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="brand in options.brands" :key="brand.id" :value="brand.slug">{{ brand.name }} ({{ brand.count }})</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.model" @click="toggleGroup('model')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Modelo</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.model ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.model" class="mt-2">
                    <select v-model="form.model" :disabled="!form.brand" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm disabled:opacity-50">
                        <option value="">{{ form.brand ? 'Todos' : 'Elige una marca primero' }}</option>
                        <option v-for="model in modelOptions" :key="model.id" :value="model.id">{{ model.name }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.version" @click="toggleGroup('version')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Versión</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.version ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.version" class="mt-2">
                    <select v-model="form.version" :disabled="!form.model" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm disabled:opacity-50">
                        <option value="">{{ form.model ? 'Todas' : 'Elige un modelo primero' }}</option>
                        <option v-for="version in versionOptions" :key="version.id" :value="version.id">{{ version.name }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.city" @click="toggleGroup('city')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Ciudad</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.city ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.city" class="mt-2">
                    <select v-model="form.city" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="city in options.cities" :key="city.value" :value="city.value">{{ city.value }} ({{ city.count }})</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.body" @click="toggleGroup('body')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Carrocería</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.body ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.body" class="mt-2 space-y-1">
                    <label v-for="body in options.bodies" :key="body.value" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input v-model="form.body" type="radio" :value="body.value" class="h-4 w-4" />
                        {{ body.value }} ({{ body.count }})
                    </label>
                    <label class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input v-model="form.body" type="radio" value="" class="h-4 w-4" /> Todas
                    </label>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.transmission" @click="toggleGroup('transmission')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Transmisión</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.transmission ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.transmission" class="mt-2">
                    <select v-model="form.transmission" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="item in options.transmissions" :key="item.value" :value="item.value">{{ item.value }} ({{ item.count }})</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.fuel" @click="toggleGroup('fuel')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Combustible</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.fuel ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.fuel" class="mt-2">
                    <select v-model="form.fuel" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todos</option>
                        <option v-for="item in options.fuels" :key="item.value" :value="item.value">{{ item.value }} ({{ item.count }})</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.color" @click="toggleGroup('color')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Color</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.color ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.color" class="mt-2">
                    <select v-model="form.color" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todos</option>
                        <option v-for="item in options.colors" :key="item.value" :value="item.value">{{ item.value }} ({{ item.count }})</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.price" @click="toggleGroup('price')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Precio (MXN)</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.price ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.price" class="mt-2 flex items-center gap-2">
                    <Input v-model="form.min_price" type="number" placeholder="Mín" class="no-spinner h-10" />
                    <span class="text-muted-foreground">–</span>
                    <Input v-model="form.max_price" type="number" placeholder="Máx" class="no-spinner h-10" />
                </div>
            </div>

            <div class="border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.year" @click="toggleGroup('year')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Año</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.year ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.year" class="mt-2 flex items-center gap-2">
                    <Input v-model="form.min_year" type="number" placeholder="Desde" class="no-spinner h-10" />
                    <span class="text-muted-foreground">–</span>
                    <Input v-model="form.max_year" type="number" placeholder="Hasta" class="no-spinner h-10" />
                </div>
            </div>

            <button
                v-if="activeFiltersCount"
                type="button"
                class="mt-4 w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm font-semibold text-secondary transition hover:border-primary/40 hover:text-primary"
                @click="clear"
            >
                Limpiar filtros
            </button>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <form
                class="mb-5 flex flex-col gap-1.5 rounded-2xl border border-border bg-card p-1 shadow-soft sm:flex-row sm:items-center sm:rounded-full sm:p-1.5"
                @submit.prevent="applyAiFilters"
            >
                <div class="flex min-w-0 flex-1 items-center gap-1.5 px-2.5">
                    <Search class="h-4 w-4 shrink-0 text-primary" />
                    <input
                        v-model="form.q"
                        placeholder="Quiero una SUV familiar, automática, de menos de $350,000…"
                        class="h-9 min-w-0 flex-1 bg-transparent text-[13px] text-secondary outline-none placeholder:text-muted-foreground sm:h-10 sm:text-sm"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button type="submit" variant="hero" size="pill" :disabled="aiLoading || !form.q.trim()" class="h-9 shrink-0 gap-1 px-3 text-[11px] sm:h-10 sm:gap-1.5 sm:px-4 sm:text-xs">
                        <LoaderCircle v-if="aiLoading" class="h-4 w-4 animate-spin" />
                        Buscar con IA <Sparkles class="h-4 w-4" />
                    </Button>
                    <Button type="button" variant="outline" size="pill" class="h-9 shrink-0 px-3 text-[11px] sm:h-10 sm:px-4 sm:text-xs lg:hidden" @click="showFilters = !showFilters">
                        <SlidersHorizontal class="h-4 w-4" /> Filtros
                    </Button>

                    <div ref="sortRef" class="relative shrink-0">
                        <button
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-full border border-input bg-background text-secondary transition hover:bg-accent sm:h-10 sm:w-10"
                            :title="`Ordenar: ${sortLabel}`"
                            :aria-label="`Ordenar: ${sortLabel}`"
                            :aria-expanded="sortOpen"
                            @click="sortOpen = !sortOpen"
                        >
                            <ArrowUpDown class="h-4 w-4" />
                        </button>
                        <div
                            v-if="sortOpen"
                            class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-2xl border border-border bg-card p-1 shadow-elegant"
                        >
                            <button
                                v-for="option in sortOptions"
                                :key="option.value"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left text-xs font-semibold transition"
                                :class="form.sort === option.value ? 'bg-accent text-primary' : 'text-secondary hover:bg-muted'"
                                @click="chooseSort(option.value)"
                            >
                                {{ option.label }}
                                <Check v-if="form.sort === option.value" class="h-3.5 w-3.5 shrink-0" />
                            </button>
                        </div>
                    </div>
                </div>
            </form>

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
