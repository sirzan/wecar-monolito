<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ArrowUpDown, Check, ChevronDown, LoaderCircle, Search, SlidersHorizontal, Sparkles, Tag, X } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import MoneyInput from '@/components/ui/MoneyInput.vue';
import ExternalCarCard from '@/components/external/ExternalCarCard.vue';
import ExternalSearchModal from '@/components/external/ExternalSearchModal.vue';
import ExternalSearchLimitModal from '@/components/external/ExternalSearchLimitModal.vue';
import SearchTokensCard from '@/components/external/SearchTokensCard.vue';
import SearchRewardAdDialog from '@/components/external/SearchRewardAdDialog.vue';
import TypingPlaceholder from '@/components/site/TypingPlaceholder.vue';
import { useExternalSearch } from '@/composables/useExternalSearch';
import { resolveSmartFilters } from '@/composables/useSmartSearch';
import { vehicleYears } from '@/lib/carCatalog';
import { formatMxn } from '@/lib/currency';
import type { ExternalCar } from '@/types/external';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    filters: Record<string, string>;
    cars: { data: CarCardData[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };
    externalCars: ExternalCar[];
    smartSearch?: boolean;
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
    min_km: props.filters.min_km ?? '',
    max_km: props.filters.max_km ?? '',
    sort: props.filters.sort ?? 'recent',
});

const sortOptions = [
    { value: 'recent', label: 'Más recientes' },
    { value: 'price-asc', label: 'Precio: menor a mayor' },
    { value: 'price-desc', label: 'Precio: mayor a menor' },
    { value: 'year-desc', label: 'Año: más nuevo' },
    { value: 'km-asc', label: 'Menos kilómetros' },
];

const searchPhrases = [
    'Toyota Corolla 2022, hasta $350,000, en Monterrey',
    'SUV automática 2021, máximo $450,000, CDMX',
    'Pickup 2020, hasta $600,000, en Guadalajara',
    'Honda Civic 2023, menos de $400,000, Querétaro',
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
    km: false,
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
const filtering = ref(false);
const aiLoading = ref(false);
const suspendWatchers = ref(false);
const smartFallback = ref(false);
const externalSearch = useExternalSearch();
const rewardOpen = ref(false);
let debounce: ReturnType<typeof setTimeout> | null = null;

const externalAsMain = computed(
    () =>
        props.cars.meta.total === 0 &&
        (externalSearch.loading.value || externalSearch.results.value.length > 0 || props.externalCars.length > 0),
);

const MIN_EXTERNAL_RESULTS = 15;

const externalBrand = computed(() => props.options.brands.find((brand) => brand.slug === form.value.brand) ?? null);
const externalModel = computed(
    () => props.options.models.find((model) => model.id === form.value.model && model.brand_id === externalBrand.value?.id) ?? null,
);
const externalExactYear = computed(() => {
    const min = form.value.min_year;
    const max = form.value.max_year;

    return min && max && min === max ? Number(min) : null;
});

const externalSearchReady = computed(
    () => externalBrand.value !== null && externalModel.value !== null && externalExactYear.value !== null,
);

const externalSearchCriteria = computed(() => {
    if (!externalSearchReady.value || !externalBrand.value || !externalModel.value || externalExactYear.value === null) {
        return null;
    }

    return {
        brand_id: externalBrand.value.id,
        model_id: externalModel.value.id,
        year: externalExactYear.value,
        ...(form.value.version ? { version_id: form.value.version } : {}),
        ...(form.value.city ? { city: form.value.city } : {}),
    };
});

const normalizeText = (value: string): string =>
    value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const strictTokens = (value: string): string[] =>
    normalizeText(value)
        .split(/[^a-z0-9]+/)
        .filter((token) => token.length > 0 && !['los', 'las', 'del', 'de', 'en', 'la', 'el', 'y', 'con', 'para', 'por'].includes(token));

const strictExternalMatches = (car: ExternalCar): boolean => {
    if (!externalSearchReady.value || !externalBrand.value || !externalModel.value || externalExactYear.value === null) {
        return false;
    }

    if (car.year !== null && Number(car.year) !== externalExactYear.value) {
        return false;
    }

    const haystack = normalizeText([car.title, car.location ?? '', car.platformName ?? ''].join(' '));
    const brandTokens = strictTokens(externalBrand.value.name);
    const modelTokens = strictTokens(externalModel.value.name);

    if (brandTokens.length === 0 || modelTokens.length === 0) return false;

    return brandTokens.every((token) => haystack.includes(token))
        && modelTokens.every((token) => haystack.includes(token));
};

const strictExternalCars = computed<ExternalCar[]>(() =>
    props.externalCars.filter((car) => strictExternalMatches(car)),
);

const tokenize = (value: string): string[] =>
    normalizeText(value)
        .split(/[^a-z0-9]+/)
        .filter((token) => token.length > 1 && !['los', 'las', 'del', 'de', 'en', 'la', 'el', 'y', 'con', 'para', 'por'].includes(token));

const externalCombined = computed<ExternalCar[]>(() => {
    if (!externalSearchReady.value) return [];

    const merged: ExternalCar[] = [];
    const seen = new Set<string>();

    const push = (car: ExternalCar) => {
        const key = car.id || car.slug || car.url || car.title;

        if (!key || seen.has(key)) return;

        seen.add(key);
        merged.push(car);
    };

    for (const car of externalSearch.results.value) push(car);
    for (const car of strictExternalCars.value) push(car);

    const minPrice = Number(form.value.min_price);
    const maxPrice = Number(form.value.max_price);
    const minKm = Number(form.value.min_km);
    const maxKm = Number(form.value.max_km);
    const queryTokens = tokenize(form.value.q);

    const matchesRanges = (car: ExternalCar): boolean => {
        const price = Number(car.price);

        if (Number.isFinite(price)) {
            if (Number.isFinite(minPrice) && price < minPrice) return false;
            if (Number.isFinite(maxPrice) && price > maxPrice) return false;
        }

        const km = Number(car.km);

        if (Number.isFinite(km)) {
            if (Number.isFinite(minKm) && km < minKm) return false;
            if (Number.isFinite(maxKm) && km > maxKm) return false;
        }

        return true;
    };

    const matchesText = (car: ExternalCar): boolean => {
        if (queryTokens.length === 0) return true;

        const haystack = tokenize([car.title, car.location ?? '', car.platformName ?? ''].join(' '));

        return queryTokens.some((token) => haystack.includes(token));
    };

    const strict = merged.filter((car) => strictExternalMatches(car) && matchesRanges(car) && matchesText(car));
    const result = [...strict];

    // Garantiza al menos 15 resultados del mismo vehículo en todas las plataformas cuando hay suficientes.
    for (const car of merged) {
        if (result.length >= MIN_EXTERNAL_RESULTS) break;
        if (result.includes(car) || !matchesRanges(car)) continue;
        result.push(car);
    }

    return result;
});

const showExternalCombined = computed(
    () => props.cars.meta.total === 0 && externalCombined.value.length > 0,
);

onMounted(async () => {
    await externalSearch.refreshStatus();

    if (!props.smartSearch) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('smart');
    window.history.replaceState(window.history.state, '', url);

    if (props.cars.meta.total === 0 && externalSearch.enabled.value && externalSearchReady.value) {
        searchExternalFallback();
    }
});

const activeFiltersCount = computed(
    () => Object.entries(form.value).filter(([key, value]) => key !== 'sort' && value).length,
);

const filterLabel = (key: string, value: string): string => {
    if (key === 'brand') return props.options.brands.find((brand) => brand.slug === value)?.name ?? value;
    if (key === 'model') return props.options.models.find((model) => model.id === value)?.name ?? value;
    if (key === 'version') return props.options.versions.find((version) => version.id === value)?.name ?? value;
    if (key === 'min_price') return `Desde ${formatMxn(value)}`;
    if (key === 'max_price') return `Hasta ${formatMxn(value)}`;
    if (key === 'min_year') return `Año desde ${value}`;
    if (key === 'max_year') return `Año hasta ${value}`;
    if (key === 'min_km') return `Km desde ${Number(value).toLocaleString('es-MX')}`;
    if (key === 'max_km') return `Km hasta ${Number(value).toLocaleString('es-MX')}`;
    if (key === 'q') return `“${value}”`;
    if (key === 'city') return `Ubicación: ${value}`;
    if (key === 'body') return `Carrocería: ${value}`;
    if (key === 'transmission') return `Transmisión: ${value}`;
    if (key === 'fuel') return `Combustible: ${value}`;
    if (key === 'color') return `Color: ${value}`;
    return value;
};

const activeChips = computed(() =>
    Object.entries(form.value)
        .filter(([key, value]) => key !== 'sort' && value)
        .map(([key, value]) => ({ key, label: filterLabel(key, String(value)) })),
);

const removeFilter = (key: string) => {
    (form.value as Record<string, string>)[key] = '';
    apply(true);
};

const normalizeRanges = () => {
    for (const [minKey, maxKey] of [
        ['min_price', 'max_price'],
        ['min_year', 'max_year'],
        ['min_km', 'max_km'],
    ] as const) {
        const min = Number(form.value[minKey]);
        const max = Number(form.value[maxKey]);

        if (form.value[minKey] && form.value[maxKey] && Number.isFinite(min) && Number.isFinite(max) && min > max) {
            [form.value[minKey], form.value[maxKey]] = [form.value[maxKey], form.value[minKey]];
        }
    }
};

const apply = (immediate = false, onSuccess?: (total: number) => void) => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(
        () => {
            normalizeRanges();
            const params: Record<string, string> = {};
            Object.entries(form.value).forEach(([key, value]) => {
                if (value) params[key] = String(value);
            });
            router.get('/comprar', params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => { filtering.value = true; },
                onFinish: () => { filtering.value = false; },
                onSuccess: (page) => {
                    const total = (page.props as { cars?: { meta?: { total?: number } } }).cars?.meta?.total ?? 0;
                    onSuccess?.(total);
                },
            });
        },
        immediate ? 0 : 350,
    );
};

watch(() => form.value.q, () => {
    if (suspendWatchers.value) return;
    apply();
});
watch(
    () => [form.value.brand, form.value.model, form.value.version, form.value.city, form.value.body, form.value.transmission, form.value.fuel, form.value.color, form.value.min_price, form.value.max_price, form.value.min_year, form.value.max_year, form.value.min_km, form.value.max_km, form.value.sort],
    () => {
        if (suspendWatchers.value) return;
        apply(true);
    },
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


const applySmartSearch = async () => {
    if (!form.value.q.trim() || aiLoading.value) return;

    aiLoading.value = true;
    suspendWatchers.value = true;

    try {
        let filters: Record<string, string>;

        try {
            filters = await resolveSmartFilters(form.value.q);
        } catch {
            filters = { q: form.value.q.trim() };
        }

        for (const key of ['brand', 'body', 'transmission', 'fuel', 'min_price', 'max_price', 'min_year', 'max_year']) {
            (form.value as Record<string, string>)[key] = filters[key] ?? '';
        }

        form.value.q = filters.q ?? form.value.q;

        await nextTick();
        suspendWatchers.value = false;
        smartFallback.value = true;

        apply(true, (total) => {
            if (total === 0 && smartFallback.value) {
                smartFallback.value = false;

                if (externalSearchReady.value) {
                    toast.info('No hay resultados en WeCar. Buscando en otras plataformas…');
                }

                searchExternalFallback();
                return;
            }

            smartFallback.value = false;
        });
    } finally {
        aiLoading.value = false;
        suspendWatchers.value = false;
    }
};


const buildExternalQuery = () => {
    if (!externalSearchReady.value || !externalBrand.value || !externalModel.value || externalExactYear.value === null) {
        return '';
    }

    const versionName = props.options.versions.find((version) => version.id === form.value.version)?.name ?? '';

    const parts = [
        externalBrand.value.name,
        externalModel.value.name,
        String(externalExactYear.value),
        versionName,
        form.value.city,
    ]
        .map((part) => part.trim())
        .filter(Boolean);

    return parts.join(' ');
};

const runExternalSearch = (options: { modal?: boolean } = {}) => {
    if (!externalSearchReady.value) {
        toast.info('Para buscar en otras plataformas selecciona marca, modelo y año exacto.');
        return;
    }

    void externalSearch.search(buildExternalQuery(), false, {
        modal: options.modal ?? true,
        criteria: externalSearchCriteria.value ?? undefined,
    });
};

const searchExternal = () => runExternalSearch();

const searchExternalFallback = () => runExternalSearch({ modal: false });

const retryExternalSearch = () => {
    if (!externalSearchReady.value) {
        toast.info('Para buscar en otras plataformas selecciona marca, modelo y año exacto.');
        return;
    }

    void externalSearch.search(externalSearch.lastQuery.value || buildExternalQuery(), true, {
        modal: true,
        criteria: externalSearchCriteria.value ?? undefined,
    });
};

const clear = () => {
    form.value = { q: '', brand: '', model: '', version: '', city: '', body: '', transmission: '', fuel: '', color: '', min_price: '', max_price: '', min_year: '', max_year: '', min_km: '', max_km: '', sort: 'recent' };
    showFilters.value = false;
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex ?? false" />

    <div v-if="showFilters" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="showFilters = false" />

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
        <aside :class="['shrink-0', showFilters ? 'fixed inset-y-0 left-0 z-50 block w-[min(88vw,340px)] overflow-y-auto bg-background p-4 shadow-2xl lg:static lg:z-auto lg:w-72 lg:overflow-visible lg:bg-transparent lg:p-0 lg:shadow-none' : 'hidden w-72 lg:block']">
            <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div class="mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-secondary">Filtros</h2>
                    <span v-if="activeFiltersCount" class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-bold text-secondary-foreground">{{ activeFiltersCount }}</span>
                </div>
                <button type="button" class="rounded-full p-1.5 text-muted-foreground hover:bg-accent lg:hidden" aria-label="Cerrar filtros" @click="showFilters = false">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col">
            <div class="order-1 border-t border-border/60 py-3 first:border-t-0 first:pt-0">
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

            <div class="order-2 border-t border-border/60 py-3">
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

            <div class="order-3 border-t border-border/60 py-3">
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

            <div class="order-5 border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.city" @click="toggleGroup('city')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Ubicación</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.city ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.city" class="mt-2">
                    <select v-model="form.city" class="h-10 w-full rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="city in options.cities" :key="city.value" :value="city.value">{{ city.value }} ({{ city.count }})</option>
                    </select>
                </div>
            </div>

            <div class="order-8 border-t border-border/60 py-3">
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

            <div class="order-9 border-t border-border/60 py-3">
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

            <div class="order-10 border-t border-border/60 py-3">
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

            <div class="order-11 border-t border-border/60 py-3">
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

            <div class="order-6 border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.price" @click="toggleGroup('price')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Precio (MXN)</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.price ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.price" class="mt-2">
                    <div class="flex items-center gap-2">
                        <MoneyInput v-model="form.min_price" placeholder="Mín" class="h-10" />
                        <span class="text-muted-foreground">–</span>
                        <MoneyInput v-model="form.max_price" placeholder="Máx" class="h-10" />
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">Disponible: {{ formatMxn(options.price.min) }} – {{ formatMxn(options.price.max) }}</p>
                </div>
            </div>

            <div class="order-7 border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.km" @click="toggleGroup('km')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Kilometraje</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.km ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.km" class="mt-2 flex items-center gap-2">
                    <MoneyInput v-model="form.min_km" :min="0" placeholder="Mín km" class="h-10" />
                    <span class="text-muted-foreground">–</span>
                    <MoneyInput v-model="form.max_km" :min="0" placeholder="Máx km" class="h-10" />
                </div>
            </div>

            <div class="order-4 border-t border-border/60 py-3">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="openGroups.year" @click="toggleGroup('year')">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Año</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="openGroups.year ? 'rotate-180' : ''" />
                </button>
                <div v-show="openGroups.year" class="mt-2 flex items-center gap-2">
                    <select v-model="form.min_year" class="h-10 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Desde</option>
                        <option v-for="year in vehicleYears" :key="`min-${year}`" :value="String(year)">{{ year }}</option>
                    </select>
                    <span class="text-muted-foreground">–</span>
                    <select v-model="form.max_year" class="h-10 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm">
                        <option value="">Hasta</option>
                        <option v-for="year in vehicleYears" :key="`max-${year}`" :value="String(year)">{{ year }}</option>
                    </select>
                </div>
            </div>

            <button
                type="button"
                :disabled="activeFiltersCount === 0"
                class="order-12 mt-4 w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm font-semibold text-secondary transition hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-border disabled:hover:text-secondary"
                @click="clear"
            >
                Limpiar filtros
            </button>
            </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <form
                class="mb-5 flex flex-col gap-1.5 rounded-2xl border border-border bg-card p-1 shadow-soft sm:flex-row sm:items-center sm:rounded-full sm:p-1.5"
                @submit.prevent="applySmartSearch"
            >
                <div class="relative flex min-w-0 flex-1 items-center gap-1.5 px-2.5">
                    <Search class="h-4 w-4 shrink-0 text-primary" />
                    <div class="relative min-w-0 flex-1">
                        <input
                            v-model="form.q"
                            aria-label="Buscar autos"
                            placeholder=""
                            class="h-9 w-full min-w-0 bg-transparent text-[13px] text-secondary outline-none sm:h-10 sm:text-sm"
                        />
                        <TypingPlaceholder
                            v-if="!form.q"
                            :phrases="searchPhrases"
                            class="text-[13px] sm:text-sm"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <Button type="submit" variant="hero" size="pill" :disabled="aiLoading || !form.q.trim()" class="h-9 shrink-0 gap-1 px-2.5 text-[10px] sm:h-9 sm:gap-1 sm:px-3 sm:text-[11px]">
                        <LoaderCircle v-if="aiLoading" class="h-3.5 w-3.5 animate-spin" />
                        {{ aiLoading ? 'Buscando…' : 'Buscar' }}
                        <Sparkles v-if="!aiLoading" class="h-3.5 w-3.5" />
                    </Button>
                    <Button type="button" variant="outline" size="pill" class="h-9 shrink-0 px-2.5 text-[10px] sm:h-9 sm:px-3 sm:text-[11px] lg:hidden" @click="showFilters = !showFilters">
                        <SlidersHorizontal class="h-3.5 w-3.5" /> Filtros
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
                    <template v-if="cars.meta.total > 0">
                        {{ cars.meta.total.toLocaleString('es-MX') }} {{ cars.meta.total === 1 ? 'auto encontrado' : 'autos encontrados' }}
                    </template>
                    <template v-else-if="externalAsMain">
                        Resultados de plataformas aliadas
                    </template>
                    <template v-else>
                        Sin resultados en el catálogo
                    </template>
                </p>
                <span v-if="filtering" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                    <LoaderCircle class="h-3.5 w-3.5 animate-spin" /> Actualizando…
                </span>
            </div>

            <div v-if="activeChips.length" class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-border bg-card px-3 py-1 text-[11px] font-semibold text-secondary transition hover:border-destructive/40 hover:text-destructive"
                    @click="removeFilter(chip.key)"
                >
                    {{ chip.label }} <X class="h-3 w-3" />
                </button>
                <button type="button" class="rounded-full px-2 py-1 text-[11px] font-bold text-primary hover:underline" @click="clear">Limpiar todo</button>
            </div>

            <div v-if="cars.meta.total === 0 && !externalSearchReady" class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-700">
                Para buscar en otras plataformas selecciona <strong>marca</strong>, <strong>modelo</strong> y <strong>año exacto</strong>.
            </div>

            <div v-if="cars.data.length" class="grid gap-5 transition-opacity sm:grid-cols-2 lg:grid-cols-3" :class="filtering ? 'pointer-events-none opacity-50' : ''">
                <CarCard v-for="car in cars.data" :key="car.id" :car="car" />
            </div>

            <div v-else-if="externalSearch.loading.value && !showExternalCombined" class="grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
                <LoaderCircle class="h-10 w-10 animate-spin text-primary" />
                <h2 class="mt-4 text-lg font-bold text-secondary">Buscando en otras plataformas…</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    No encontramos autos en nuestro catálogo. Estamos revisando Mercado Libre, Seminuevos y más.
                </p>
            </div>

            <div v-else-if="showExternalCombined" class="space-y-5">
                <div class="rounded-2xl border border-primary/20 bg-accent/40 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">
                        {{ externalCombined.length }} autos encontrados en otras plataformas
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        No encontramos autos en el catálogo WeCar. Te mostramos resultados de todas las plataformas aliadas, ordenados por coincidencia con tus filtros.
                    </p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <ExternalCarCard v-for="car in externalCombined" :key="car.id" :car="car" @open="externalSearch.openExternal" />
                </div>
            </div>

            <div v-else class="grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
                <X class="h-10 w-10 text-muted-foreground" />
                <h2 class="mt-4 text-lg font-bold text-secondary">Sin resultados</h2>
                <p class="mt-2 max-w-md text-sm text-muted-foreground">
                    Prueba ajustando los filtros o ampliando el rango de precio y año.
                </p>
                <Button class="mt-5 rounded-full" @click="clear">Limpiar filtros</Button>
                <p v-if="externalSearch.error.value" class="mt-4 text-xs font-semibold text-destructive">{{ externalSearch.error.value }}</p>
            </div>

            <Pagination :current-page="cars.meta.current_page" :last-page="cars.meta.last_page" :base-query="filters" />

            <section v-if="(props.externalCars.length || externalSearch.enabled.value) && !externalAsMain" class="mt-12 border-t border-border pt-8">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">Búsqueda inteligente</p>
                        <h2 class="mt-1 text-xl font-black text-secondary">Autos encontrados en otras plataformas</h2>
                    </div>
                </div>

                <div v-if="strictExternalCars.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <ExternalCarCard v-for="car in strictExternalCars" :key="car.id" :car="car" @open="externalSearch.openExternal" />
                </div>
                <p v-else-if="!externalSearchReady" class="mt-4 text-sm text-muted-foreground">Selecciona marca, modelo y año exacto para ver autos guardados de otras plataformas.</p>
                <p v-else class="mt-4 text-sm text-muted-foreground">Aún no hay autos externos guardados para este vehículo. Realiza una búsqueda para encontrarlos.</p>

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
        :platforms="externalSearch.status.value?.platforms ?? []"
        @close="externalSearch.closeModal"
        @open="externalSearch.openExternal"
        @retry="retryExternalSearch"
    />

    <ExternalSearchLimitModal
        :open="externalSearch.showLimitModal.value"
        :status="externalSearch.status.value"
        @close="externalSearch.closeLimitModal"
        @reward="externalSearch.closeLimitModal(); rewardOpen = true"
    />

    <SearchRewardAdDialog :open="rewardOpen" @close="rewardOpen = false" @reward="externalSearch.refreshStatus" />
</template>
