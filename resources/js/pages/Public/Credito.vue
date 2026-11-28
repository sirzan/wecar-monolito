<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, FileCheck, Percent, ShieldCheck } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Label from '@/components/ui/Label.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import FinancialPartnersCarousel from '@/components/site/FinancialPartnersCarousel.vue';
import { useCarVersions } from '@/composables/useCarVersions';
import { loadCreditDraft, saveCreditDraft } from '@/composables/useCreditDraft';
import { vehicleYears } from '@/lib/carCatalog';
import { monthlyPayment, serviceFee, SIMULATOR_ANNUAL_RATE } from '@/lib/creditCalculator';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    financieras: { id: string; slug: string; nombre: string; logo: string | null; tasa_min: number | null; tasa_max: number | null; plazo_max: number | null; enganche_pct: number | null }[];
    brands: { id: string; name: string; slug: string; models: { id: string; name: string }[] }[];
    seo: { title: string; description: string; canonical: string };
}>();

const DRAFT_SCOPE = 'credito';

const page = usePage<{ auth?: { user: { id: string } | null } }>();
const query = new URL(page.url, 'http://localhost').searchParams;
const priceFromQuery = Number(query.get('precio') ?? 0);
const yearFromQuery = Number(query.get('year') ?? 0);
const brandFromQuery = props.brands.find((item) => item.id === query.get('brand_id') || item.name === query.get('brand'));
const modelFromQuery = brandFromQuery?.models.find((item) => item.id === query.get('model_id') || item.name === query.get('model'));
const hasVehicleQuery = Boolean(brandFromQuery || modelFromQuery || yearFromQuery > 0 || priceFromQuery > 0);

const price = ref(priceFromQuery > 0 ? priceFromQuery : 280000);
const downPaymentPct = ref(20);
const term = ref(48);
const brand = ref(brandFromQuery?.name ?? '');
const model = ref(modelFromQuery?.name ?? '');
const version = ref(query.get('version') ?? '');
const year = ref<number | null>(yearFromQuery > 0 ? yearFromQuery : null);
const km = ref(Number(query.get('km') ?? 0));
const carId = ref(query.get('car_id') ?? '');
const dialogOpen = ref(false);
const isHydrating = ref(true);

const {
    versions,
    loading: versionsLoading,
    error: versionsError,
    load: fetchVersions,
    scheduleLoad: scheduleVersionsLoad,
} = useCarVersions({ fast: true, debounceMs: 150 });

const models = computed(() => props.brands.find((item) => item.name === brand.value)?.models ?? []);
const selectedBrandId = computed(() => props.brands.find((item) => item.name === brand.value)?.id ?? '');
const selectedModelId = computed(() => models.value.find((item) => item.name === model.value)?.id ?? '');
const versionSelectOptions = computed(() => {
    if (version.value && !versions.value.some((item) => item.name === version.value)) {
        return [{ id: 'selected-version', name: version.value }, ...versions.value];
    }

    return versions.value;
});
const selectedVersionId = computed(() => versions.value.find((item) => item.name === version.value)?.id ?? query.get('version_id') ?? '');
const downPayment = computed(() => Math.round((price.value * downPaymentPct.value) / 100));
const financed = computed(() => Math.max(price.value - downPayment.value, 0));
const monthly = computed(() => monthlyPayment(financed.value, SIMULATOR_ANNUAL_RATE, term.value));
const serviceFeeAmount = computed(() => serviceFee(financed.value));

const loadVersions = async (force = false) => {
    await fetchVersions({
        brandId: selectedBrandId.value,
        modelId: selectedModelId.value,
        year: year.value,
        force,
    });
};

const persistSimulatorDraft = () => {
    if (isHydrating.value) {
        return;
    }

    saveCreditDraft(DRAFT_SCOPE, {
        simulator: {
            year: year.value,
            brand: brand.value,
            brand_id: selectedBrandId.value,
            model: model.value,
            model_id: selectedModelId.value,
            version: version.value,
            version_id: selectedVersionId.value,
            price: price.value,
            downPaymentPct: downPaymentPct.value,
            term: term.value,
            km: km.value,
            carId: carId.value,
        },
    });
};

const openCreditDialog = () => {
    persistSimulatorDraft();
    dialogOpen.value = true;
};

watch(
    () => [brand.value, model.value, year.value],
    (current, previous) => {
        if (!isHydrating.value && previous && (current[0] !== previous[0] || current[1] !== previous[1] || current[2] !== previous[2])) {
            version.value = '';
        }

        scheduleVersionsLoad({
            brandId: selectedBrandId.value,
            modelId: selectedModelId.value,
            year: year.value,
        });
    },
);

watch(
    () => [year.value, brand.value, model.value, version.value, price.value, downPaymentPct.value, term.value],
    () => {
        persistSimulatorDraft();
    },
);

onMounted(() => {
    const draft = loadCreditDraft(DRAFT_SCOPE);

    if (draft?.simulator && !hasVehicleQuery) {
        const savedBrand = props.brands.find(
            (item) => item.id === draft.simulator?.brand_id || item.name === draft.simulator?.brand,
        );
        const savedModel = savedBrand?.models.find(
            (item) => item.id === draft.simulator?.model_id || item.name === draft.simulator?.model,
        );

        if (draft.simulator.year) {
            year.value = draft.simulator.year;
        }
        if (savedBrand) {
            brand.value = savedBrand.name;
        }
        if (savedModel) {
            model.value = savedModel.name;
        }
        if (draft.simulator.version) {
            version.value = draft.simulator.version;
        }
        if (typeof draft.simulator.price === 'number' && draft.simulator.price > 0) {
            price.value = draft.simulator.price;
        }
        if (typeof draft.simulator.downPaymentPct === 'number' && draft.simulator.downPaymentPct > 0) {
            downPaymentPct.value = draft.simulator.downPaymentPct;
        }
        if (typeof draft.simulator.term === 'number' && draft.simulator.term > 0) {
            term.value = draft.simulator.term;
        }
        if (typeof draft.simulator.km === 'number' && draft.simulator.km >= 0) {
            km.value = draft.simulator.km;
        }
        if (draft.simulator.carId) {
            carId.value = draft.simulator.carId;
        }
    }

    isHydrating.value = false;

    if (selectedModelId.value && year.value) {
        void loadVersions();
    }

    if (draft?.autoReopen && page.props.auth?.user) {
        dialogOpen.value = true;
    }
});

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const benefits = [
    { icon: Percent, title: 'Tasas competitivas', description: 'Desde la mejor tasa disponible, sujeta a evaluación y sin sorpresas durante el plazo.' },
    { icon: Clock, title: 'Respuesta rápida', description: 'Aprobación estimada de 24 a 48 hrs, con documentación mínima y todo en línea.' },
    { icon: ShieldCheck, title: 'Plazos y protección', description: 'Hasta 72 meses, enganches desde el 10% y seguro del auto incluido en tu mensualidad.' },
    { icon: FileCheck, title: 'Precalificación sencilla', description: 'Evaluamos tu perfil de forma rápida con IA para darte opciones reales de financiamiento.' },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="relative isolate overflow-hidden px-4 py-10 sm:px-6 sm:py-16 lg:py-24">
        <img src="/images/credito-hero.jpg" alt="Crédito automotriz Wecar" class="absolute inset-0 -z-20 h-full w-full object-cover" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/80 via-secondary/70 to-background" />

        <div class="mx-auto max-w-4xl text-center text-white">
            <p class="text-xs font-bold tracking-wider text-primary sm:text-sm">Créditos automotrices</p>
            <h1 class="page-title text-shadow-hero-soft mt-2 text-white">Tu auto, tu plan.</h1>
            <p class="page-subtitle mt-4 text-white/90">
                Aprobación rápida, mensualidades a tu medida y el mejor financiamiento para ti.
            </p>

            <div class="mx-auto mt-6 max-w-md overflow-hidden rounded-2xl border border-primary/20 bg-card text-left shadow-elegant">
                <div class="p-4 text-foreground sm:p-5">
                    <h2 class="text-lg font-black text-secondary sm:text-xl">Simula tu crédito</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Ajusta los valores para estimar tu mensualidad.</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <Label class="font-normal">Año</Label>
                            <select v-model.number="year" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm">
                                <option :value="null">Selecciona el año</option>
                                <option v-for="item in vehicleYears" :key="item" :value="item">{{ item }}</option>
                            </select>
                        </div>
                        <div>
                            <Label class="font-normal">Marca</Label>
                            <select v-model="brand" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" @change="model = ''">
                                <option value="">Marca</option>
                                <option v-for="item in brands" :key="item.id" :value="item.name">{{ item.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label class="font-normal">Modelo</Label>
                            <select v-model="model" :disabled="!brand" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm disabled:opacity-50">
                                <option value="">Modelo</option>
                                <option v-for="item in models" :key="item.id" :value="item.name">{{ item.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label class="font-normal">Versión (opcional)</Label>
                            <select
                                v-model="version"
                                :disabled="!model || !year || (versionsLoading && versionSelectOptions.length === 0)"
                                class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm disabled:opacity-50"
                            >
                                <option value="">
                                    {{ !model || !year
                                        ? 'Opcional (elige modelo y año)'
                                        : versionsLoading
                                        ? 'Cargando versiones…'
                                        : versionSelectOptions.length
                                        ? 'Sin especificar (opcional)'
                                        : 'Sin versiones (opcional)' }}
                                </option>
                                <option v-for="item in versionSelectOptions" :key="item.id" :value="item.name">{{ item.name }}</option>
                            </select>
                            <p v-if="versionsError" class="mt-1 text-[11px] font-semibold text-destructive">{{ versionsError }}</p>
                            <button
                                v-if="!versionsLoading && versionSelectOptions.length === 0 && model && year"
                                type="button"
                                class="mt-1 text-[11px] font-bold text-primary hover:underline"
                                @click="loadVersions(true)"
                            >
                                Reintentar
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label class="font-normal">Precio del auto</Label>
                                <span class="text-sm text-gray-700">{{ formatPrice(price) }}</span>
                            </div>
                            <input v-model.number="price" type="range" min="100000" max="2000000" step="10000" class="range-brand mt-2 w-full" />
                        </div>
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label class="font-normal">Enganche {{ downPaymentPct }}%</Label>
                                <span class="text-sm text-gray-700">{{ formatPrice(downPayment) }}</span>
                            </div>
                            <input v-model.number="downPaymentPct" type="range" min="10" max="80" step="5" class="range-brand mt-2 w-full" />
                        </div>
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label class="font-normal">Plazo</Label>
                                <span class="text-sm text-gray-700">{{ term }} meses</span>
                            </div>
                            <input v-model.number="term" type="range" min="12" max="72" step="12" class="range-brand mt-2 w-full" />
                        </div>
                    </div>
                </div>

                <div class="bg-secondary p-4 text-secondary-foreground sm:p-5">
                    <p class="text-[11px] font-bold text-primary">Mensualidad estimada</p>
                    <div class="mt-1 flex items-end gap-2">
                        <strong class="text-3xl font-medium sm:text-4xl">{{ formatPrice(monthly) }}</strong>
                        <span class="pb-1 text-xs text-secondary-foreground/70">/ mes</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-secondary-foreground/15 pt-3">
                        <div>
                            <p class="text-[10px] text-secondary-foreground/60">Tasa aplicada</p>
                            <p class="mt-0.5 text-sm font-normal">{{ SIMULATOR_ANNUAL_RATE }}% anual</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-secondary-foreground/60">Monto a financiar</p>
                            <p class="mt-0.5 text-sm font-normal">{{ formatPrice(financed) }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-secondary-foreground/15 pt-3">
                        <p class="text-[10px] text-secondary-foreground/60">Cargo por servicio (2.5%)</p>
                        <p class="text-sm font-normal">{{ formatPrice(serviceFeeAmount) }}</p>
                    </div>
                    <Button variant="hero" size="lg" class="mt-4 w-full" :disabled="!model" @click="openCreditDialog">
                        Completar perfil y comparar
                    </Button>
                    <p class="mt-2.5 text-center text-[11px] text-secondary-foreground/70">
                        Cargo por servicio desde 2.5% · Cotización referencial sujeta a evaluación.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div v-for="(step, index) in ['Cuéntanos de ti', 'Compara ofertas', 'Elige y solicita']" :key="step" class="flex items-center gap-3 rounded-2xl border border-border bg-card/70 px-4 py-3 text-left backdrop-blur">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-primary text-sm font-black text-primary-foreground">{{ index + 1 }}</span>
                <div class="leading-tight">
                    <p class="text-sm font-extrabold text-secondary">{{ step }}</p>
                    <p class="text-xs text-muted-foreground">{{ ['Datos básicos y perfil', 'Bancos y financieras', 'La mejor para tu perfil'][index] }}</p>
                </div>
                <CheckCircle2 class="ml-auto h-4 w-4 text-primary/60" />
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-white/15 bg-secondary/90 px-4 py-5 shadow-soft backdrop-blur">
            <FinancialPartnersCarousel :inverse="true" />
        </div>
    </section>

    <section class="bg-muted/40 px-4 py-12 sm:px-6 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <div class="text-center">
                <h2 class="section-title text-secondary">Beneficios del <span class="text-primary">crédito de auto</span></h2>
                <p class="section-subtitle mt-3 font-semibold text-muted-foreground">
                    Más de <span class="font-black text-secondary">20 bancos</span> y <span class="font-black text-secondary">30 financieras</span> afiliados
                </p>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="benefit in benefits" :key="benefit.title" class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:-translate-y-1 hover:shadow-elegant">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-gradient-primary text-primary-foreground shadow-soft">
                        <component :is="benefit.icon" class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-extrabold text-secondary">{{ benefit.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ benefit.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <FinanciarAutoDialog
        :open="dialogOpen"
        :draft-scope="DRAFT_SCOPE"
        :brands="brands"
        :car="carId ? { id: carId, brand, brand_id: selectedBrandId, model, model_id: selectedModelId, version, version_id: selectedVersionId, year, price, km } : null"
        :initial="{
            downPayment: downPaymentPct,
            term,
            brand,
            brand_id: selectedBrandId,
            model,
            model_id: selectedModelId,
            version,
            version_id: selectedVersionId,
            year: year ?? undefined,
            km,
            price,
        }"
        @close="dialogOpen = false"
    />
</template>
