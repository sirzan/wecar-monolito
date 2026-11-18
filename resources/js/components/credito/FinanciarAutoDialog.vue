<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    FileText,
    Loader2,
    ShieldCheck,
    Upload,
    X,
} from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import FormFieldError from '@/components/ui/FormFieldError.vue';
import MoneyInput from '@/components/ui/MoneyInput.vue';
import PhoneInput from '@/components/ui/PhoneInput.vue';
import ZipCodeFields from '@/components/forms/ZipCodeFields.vue';
import { vehicleYears } from '@/lib/carCatalog';
import { formatMxn } from '@/lib/currency';
import { cn } from '@/lib/utils';

interface Brand {
    id: string;
    name: string;
    models: { id: string; name: string }[];
}

interface CarInput {
    id?: string;
    brand?: string | null;
    brand_id?: string | null;
    model?: string | null;
    model_id?: string | null;
    version?: string | null;
    version_id?: string | null;
    year?: number | null;
    price?: number | null;
    km?: number | null;
    title?: string | null;
    condition?: 'new' | 'used' | null;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        car?: CarInput | null;
        brands?: Brand[];
        initial?: {
            downPayment?: number;
            term?: number;
            brand?: string;
            brand_id?: string;
            model?: string;
            model_id?: string;
            version?: string;
            version_id?: string;
            year?: number;
            km?: number | null;
            price?: number;
        };
    }>(),
    {
        car: null,
        brands: () => [],
        initial: () => ({}),
    },
);

const emit = defineEmits<{ close: [] }>();

const page = usePage<{ auth: { user: { id: string; name: string; email: string } | null } }>();
const user = computed(() => page.props.auth.user);
const stepLabels = ['Datos', 'Precalificación', 'Ofertas', 'Cuenta', 'Solicitud'];

type Step = 1 | 2 | 3 | 4 | 5;
const step = ref<Step>(1);
const evaluating = ref(false);
const submitting = ref(false);
const submitError = ref<string | null>(null);
const successFolio = ref<string | null>(null);
const showAllOffers = ref(false);
const accountCompleted = ref(false);
const documentsOpen = ref(true);
const loginPromptOpen = ref(false);
const fieldErrors = ref<Record<string, string>>({});

const fieldInvalid = (field: string): boolean => Boolean(fieldErrors.value[field]);
const fieldMessage = (field: string): string => fieldErrors.value[field] ?? '';

const focusField = (field: string) => {
    void nextTick(() => {
        const element = document.querySelector<HTMLElement>(`[data-field="${field}"]`) ?? document.getElementById(field);
        element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element?.focus({ preventScroll: true });
    });
};

const applyFieldErrors = (errors: Record<string, string>, message: string): boolean => {
    fieldErrors.value = errors;

    if (Object.keys(errors).length) {
        submitError.value = message;
        focusField(Object.keys(errors)[0]);
        return false;
    }

    return true;
};

const basics = reactive({
    phone: '',
    downPayment: props.initial.downPayment ?? 20,
    term: props.initial.term ?? 48,
});

const profile = reactive({
    income: null as number | null,
    tenure: null as number | null,
    bureau: '',
    employment: '',
    debts: null as number | null,
    age: null as number | null,
    brand: props.car?.brand ?? props.initial.brand ?? '',
    brandId: props.car?.brand_id ?? props.initial.brand_id ?? '',
    model: props.car?.model ?? props.initial.model ?? '',
    modelId: props.car?.model_id ?? props.initial.model_id ?? '',
    year: props.car?.year ?? props.initial.year ?? (null as number | null),
    price: props.car?.price ?? props.initial.price ?? (null as number | null),
    version: props.car?.version ?? props.initial.version ?? '',
    versionId: props.car?.version_id ?? props.initial.version_id ?? '',
});

const modelOptions = computed(() => {
    const brand = props.brands.find((item) => item.id === profile.brandId || item.name === profile.brand);

    return brand?.models ?? [];
});

const syncFromInitial = () => {
    const initial = props.initial;
    const car = props.car;

    basics.downPayment = initial.downPayment ?? 20;
    basics.term = initial.term ?? 48;

    profile.brand = car?.brand ?? initial.brand ?? '';
    profile.model = car?.model ?? initial.model ?? '';
    profile.version = car?.version ?? initial.version ?? '';
    profile.year = car?.year ?? initial.year ?? null;
    profile.price = car?.price ?? initial.price ?? null;

    const brand = props.brands.find((item) => item.id === (car?.brand_id ?? initial.brand_id) || item.name === profile.brand);
    profile.brandId = car?.brand_id ?? initial.brand_id ?? brand?.id ?? '';
    const model = brand?.models.find((item) => item.id === (car?.model_id ?? initial.model_id) || item.name === profile.model);
    profile.modelId = car?.model_id ?? initial.model_id ?? model?.id ?? '';
    profile.versionId = car?.version_id ?? initial.version_id ?? '';
};

const resolveModelName = () => {
    const brand = props.brands.find((item) => item.id === profile.brandId);
    const model = brand?.models.find((item) => item.id === profile.modelId);

    if (brand) profile.brand = brand.name;
    if (model) profile.model = model.name;
};

const onBrandChange = () => {
    profile.model = '';
    profile.modelId = '';
    profile.version = '';
    profile.versionId = '';
    resolveModelName();
};

const onModelChange = () => {
    profile.version = '';
    profile.versionId = '';
    resolveModelName();
};

const versionOptions = ref<{ id: string; name: string }[]>([]);
const versionsLoading = ref(false);
const versionsError = ref('');
let versionsTimer: ReturnType<typeof setTimeout> | null = null;

const loadVersions = async () => {
    if ((!profile.modelId && (!profile.brand || !profile.model)) || !profile.year) {
        versionOptions.value = [];
        return;
    }

    versionsLoading.value = true;
    versionsError.value = '';

    try {
        const params = new URLSearchParams({
            year: String(profile.year),
        });

        if (profile.modelId) {
            params.set('model_id', profile.modelId);
        } else {
            params.set('brand', profile.brand);
            params.set('model', profile.model);
        }

        const response = await fetch(`/api/car-versions?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.error || 'No pudimos cargar las versiones.');
        }

        versionOptions.value = payload.versions ?? [];

        const carVersion = props.car?.version;

        if (carVersion && !versionOptions.value.some((version) => version.name === carVersion)) {
            versionOptions.value = [{ id: 'car-version', name: carVersion }, ...versionOptions.value];
        }

        if (profile.version && !versionOptions.value.some((version) => version.name === profile.version) && !props.car?.version) {
            profile.version = '';
        }
    } catch (error) {
        versionOptions.value = props.car?.version ? [{ id: 'car-version', name: props.car.version }] : [];
        versionsError.value = error instanceof Error ? error.message : 'No pudimos cargar las versiones.';
    } finally {
        versionsLoading.value = false;
    }
};

watch(
    () => [profile.brandId, profile.modelId, profile.year],
    (value, oldValue) => {
        if (oldValue && (value[0] !== oldValue[0] || value[1] !== oldValue[1] || value[2] !== oldValue[2])) {
            profile.version = props.car?.version ?? '';
            profile.versionId = props.car?.version_id ?? '';
        }

        if (versionsTimer) clearTimeout(versionsTimer);
        versionsTimer = setTimeout(loadVersions, 600);
    },
    { immediate: true },
);

const application = reactive({
    full_name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    phone: '',
    birthdate: '',
    rfc: '',
    curp: '',
    calle: '',
    colonia: '',
    cp: '',
    alcaldia: '',
    ciudad: '',
    state: '',
    pais: 'México',
    ocupacion: '',
    empresa: '',
    antiguedad: '',
    ingreso_mensual: 0,
    deuda_mensual: 0,
    banco: '',
    tipo_cuenta: '',
});

type DocSlot = 'ine' | 'domicilio' | 'estado_cuenta';
const docLabels: Record<DocSlot, string> = {
    ine: 'INE (frente y reverso)',
    domicilio: 'Comprobante de domicilio',
    estado_cuenta: 'Estado de cuenta / ingresos',
};

const documents = reactive<Record<DocSlot, { file?: File; saved?: boolean; name?: string; error?: string }>>({
    ine: {},
    domicilio: {},
    estado_cuenta: {},
});

const offers = ref<any[]>([]);
const pawnOffers = ref<any[]>([]);
const evaluation = ref<any | null>(null);
const selectedSlug = ref<string | null>(null);

const selectedOffer = computed(() => offers.value.find((offer) => offer.slug === selectedSlug.value) ?? null);
const visibleOffers = computed(() => offers.value.slice(0, showAllOffers.value ? 8 : 5));
const hiddenOffersCount = computed(() => Math.max(offers.value.length - visibleOffers.value.length, 0));
const savedDocumentsCount = computed(() =>
    (['ine', 'domicilio', 'estado_cuenta'] as DocSlot[]).filter((slot) => documents[slot].saved).length,
);
const documentProgress = computed(() => (accountCompleted.value ? 1 : 0) + savedDocumentsCount.value);
const summaryRate = computed(() => Number(selectedOffer.value?.tasaEstimadaAnual ?? 12.5));
const price = computed(() => Number(profile.price || 0));
const downPaymentAmount = computed(() => Math.round((price.value * basics.downPayment) / 100));
const financedAmount = computed(() => Math.max(price.value - downPaymentAmount.value, 0));
const monthlyEstimate = computed(() => {
    const rate = 0.125 / 12;
    if (financedAmount.value <= 0) return 0;
    return Math.round((financedAmount.value * rate) / (1 - Math.pow(1 + rate, -basics.term)));
});

const eligibleMonthly = computed(() => {
    const values = offers.value
        .filter((offer) => offer.califica)
        .map((offer) => Number(offer.mensualidad))
        .filter((value) => Number.isFinite(value) && value > 0);

    return values.length ? Math.min(...values) : monthlyEstimate.value;
});

const normalizeOffer = (offer: any) => {
    const mensualidad = Number(offer.mensualidad ?? offer.mensualidadEstimada ?? 0);
    const tasa = Number(offer.tasa ?? offer.tasaEstimadaAnual ?? 0);

    return {
        ...offer,
        mensualidad: Number.isFinite(mensualidad) ? mensualidad : 0,
        tasa: Number.isFinite(tasa) ? tasa : 0,
        tasaEstimadaAnual: Number.isFinite(tasa) ? tasa : 0,
        cat: Number.isFinite(Number(offer.cat)) ? Number(offer.cat) : 0,
        total: Number.isFinite(Number(offer.total)) ? Number(offer.total) : 0,
        cargoServicio: Number.isFinite(Number(offer.cargoServicio)) ? Number(offer.cargoServicio) : 0,
    };
};

const formatPrice = (value: number) => {
    const amount = Number(value);

    if (!Number.isFinite(amount)) return '—';

    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(amount);
};

const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const reset = () => {
    step.value = 1;
    evaluating.value = false;
    submitting.value = false;
    submitError.value = null;
    successFolio.value = null;
    offers.value = [];
    pawnOffers.value = [];
    evaluation.value = null;
    selectedSlug.value = null;
    showAllOffers.value = false;
    accountCompleted.value = false;
    documentsOpen.value = true;
    fieldErrors.value = {};
    documents.ine = {};
    documents.domicilio = {};
    documents.estado_cuenta = {};
};

const loadProfile = async () => {
    if (!user.value) return;

    try {
        const response = await fetch('/api/credito/perfil', { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        const saved = data.profile ?? {};

        application.full_name = saved.full_name ?? user.value.name ?? '';
        application.email = saved.email ?? user.value.email ?? '';
        application.birthdate = saved.birthdate ?? '';
        application.rfc = saved.rfc ?? '';
        application.curp = saved.curp ?? '';
        application.calle = saved.calle ?? '';
        application.colonia = saved.colonia ?? '';
        application.cp = saved.cp ?? '';
        application.alcaldia = saved.alcaldia ?? '';
        application.state = saved.state ?? '';
        application.ocupacion = saved.ocupacion ?? '';
        application.empresa = saved.empresa ?? '';
        application.antiguedad = saved.antiguedad ?? '';
        application.phone = saved.phone ?? application.phone;

        if (saved.phone && !basics.phone) {
            basics.phone = saved.phone;
        }

        profile.income = saved.ingreso_mensual !== null && saved.ingreso_mensual !== undefined
            ? Number(saved.ingreso_mensual)
            : profile.income;
        profile.debts = saved.deuda_mensual !== null && saved.deuda_mensual !== undefined
            ? Number(saved.deuda_mensual)
            : profile.debts;
    } catch {
        // El usuario puede completar sus datos manualmente.
    }
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
            syncFromInitial();
            loginPromptOpen.value = !user.value;
            application.full_name = user.value?.name ?? '';
            application.email = user.value?.email ?? '';
            application.phone = basics.phone;
            void loadSavedDocuments();
            void loadProfile();
        }
    },
    { immediate: true },
);

watch(
    () => basics.phone,
    (value) => {
        application.phone = value;
    },
);

const loadSavedDocuments = async () => {
    if (!user.value) return;

    try {
        const response = await fetch('/api/credito/documentos', { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();

        for (const item of data.documents ?? []) {
            if (item.available && (item.slot === 'ine' || item.slot === 'domicilio' || item.slot === 'estado_cuenta')) {
                documents[item.slot as DocSlot] = { saved: true, name: item.name ?? 'Documento guardado' };
            }
        }
    } catch {
        // Sin documentos guardados.
    }
};

const uploadDocument = async (slot: DocSlot, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    if (file.type !== 'application/pdf') {
        documents[slot] = { error: 'Solo se permite PDF' };
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        documents[slot] = { error: 'Máximo 5 MB' };
        return;
    }

    documents[slot] = { file, name: file.name };

    const formData = new FormData();
    formData.append('slot', slot);
    formData.append('file', file);

    try {
        const response = await fetch('/api/credito/documentos', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: formData,
        });

        if (!response.ok) throw new Error('No se pudo subir el documento.');

        documents[slot] = { saved: true, name: file.name };
    } catch {
        documents[slot] = { error: 'No se pudo subir el documento.', name: file.name };
    }
};

const goToProfile = () => {
    if (basics.downPayment < 10 || basics.downPayment > 80) {
        submitError.value = 'El enganche debe estar entre 10% y 80%.';
        return;
    }

    submitError.value = null;
    step.value = 2;
};

const evaluate = async () => {
    const errors: Record<string, string> = {};

    if (profile.income === null || profile.income <= 0) errors.income = 'Ingresa tu ingreso mensual.';
    if (profile.age === null || profile.age < 18 || profile.age > 99) errors.age = 'Ingresa una edad válida (18 a 99 años).';
    if (!profile.employment) errors.employment = 'Selecciona tu tipo de empleo.';
    if (!profile.bureau) errors.bureau = 'Selecciona tu historial crediticio.';
    if (profile.tenure === null || profile.tenure < 0) errors.tenure = 'Ingresa tu antigüedad laboral.';
    if (!profile.year) errors.year = 'Selecciona el año del vehículo.';
    if (!profile.price) errors.price = 'Ingresa el precio del vehículo.';

    const labels: Record<string, string> = {
        income: 'ingreso mensual', age: 'edad', employment: 'tipo de empleo', bureau: 'historial crediticio',
        tenure: 'antigüedad laboral', year: 'año del vehículo', price: 'precio del vehículo',
    };

    const missing = Object.keys(errors).map((field) => labels[field]).filter(Boolean);

    if (!applyFieldErrors(errors, `Completa: ${missing.join(', ')}.`)) return;

    submitError.value = null;
    evaluating.value = true;

    try {
        resolveModelName();

        const response = await fetch('/api/credito/evaluar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                auto: {
                    marca: profile.brand,
                    modelo: profile.model,
                    version: profile.version || null,
                    anio: Number(profile.year),
                    precio: price.value,
                    km: props.car?.km ?? props.initial?.km ?? 0,
                    tipo: props.car?.condition === 'new' ? 'nuevo' : 'seminuevo',
                },
                perfil: {
                    ingresoMensual: Number(profile.income),
                    enganche: downPaymentAmount.value,
                    plazoMeses: basics.term,
                    historialBuro: profile.bureau,
                    tipoEmpleo: profile.employment,
                    antiguedadLaboralAnios: Number(profile.tenure),
                    deudasMensuales: Number(profile.debts ?? 0),
                    edad: Number(profile.age),
                },
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message ?? data.errors?.perfil?.[0] ?? 'No se pudo evaluar tu perfil.');
        }

        evaluation.value = data;
        offers.value = (evaluation.value.opcionesRecomendadas ?? []).map(normalizeOffer);
        pawnOffers.value = (evaluation.value.opcionesPrendarias ?? []).map(normalizeOffer);
        selectedSlug.value = offers.value.find((offer) => offer.califica)?.slug ?? null;
        showAllOffers.value = false;
        accountCompleted.value = false;
        step.value = 3;
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'No se pudo evaluar tu perfil.';
    } finally {
        evaluating.value = false;
    }
};

const goToApplication = () => {
    if (!selectedOffer.value || !selectedOffer.value.califica) {
        submitError.value = 'Selecciona una financiera elegible para continuar.';
        return;
    }

    if (!user.value) {
        loginPromptOpen.value = true;
        return;
    }

    submitError.value = null;
    accountCompleted.value = false;
    step.value = 4;
};

const goToDocuments = () => {
    const required: [string, string, string | null | undefined][] = [
        ['full_name', 'nombre completo', application.full_name],
        ['email', 'correo', application.email],
        ['phone', 'teléfono', application.phone],
        ['birthdate', 'fecha de nacimiento', application.birthdate],
        ['calle', 'calle y número', application.calle],
        ['colonia', 'colonia', application.colonia],
        ['cp', 'código postal', application.cp],
        ['alcaldia', 'alcaldía o municipio', application.alcaldia],
        ['ocupacion', 'ocupación', application.ocupacion],
    ];

    const errors: Record<string, string> = {};
    const missing: string[] = [];

    for (const [field, label, value] of required) {
        if (!String(value ?? '').trim()) {
            errors[field] = 'Este campo es obligatorio.';
            missing.push(label);
        }
    }

    if (!applyFieldErrors(errors, `Completa: ${missing.join(', ')}.`)) return;

    submitError.value = null;
    accountCompleted.value = true;
    documentsOpen.value = true;
    step.value = 5;
};

const printQuote = () => {
    window.print();
};

const submit = async () => {
    if (!selectedOffer.value) return;

    if (!user.value) {
        loginPromptOpen.value = true;
        return;
    }

    const requiredDocs = (['ine', 'domicilio', 'estado_cuenta'] as DocSlot[]).every(
        (slot) => documents[slot].saved,
    );

    if (!requiredDocs) {
        submitError.value = 'Sube los 3 documentos en PDF para continuar.';
        return;
    }

    submitError.value = null;
    submitting.value = true;

    try {
        const payload = {
            ...application,
            phone: application.phone || basics.phone,
            ingreso_mensual: Number(profile.income),
            deuda_mensual: Number(profile.debts),
            car_brand: profile.brand,
            car_model: profile.model,
            car_version: profile.version || props.car?.version || null,
            financiera_id: selectedOffer.value.id,
            car_id: props.car?.id && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(props.car.id) ? props.car.id : null,
            car_condition: props.car?.condition === 'new' ? 'new' : 'used',
            bureau: profile.bureau,
            employment: profile.employment,
            tenure: Number(profile.tenure),
            age: Number(profile.age),
            car_year: Number(profile.year),
            car_price: price.value,
            car_km: props.car?.km ?? 0,
            financiera_name: selectedOffer.value.nombre,
            score: evaluation.value?.scorePerfilEstimado ?? 0,
            risk_level: evaluation.value?.nivelRiesgo ?? null,
            down_payment: downPaymentAmount.value,
            term_months: selectedOffer.value.plazoEfectivo ?? basics.term,
            monthly_payment: selectedOffer.value.mensualidad,
            rate: selectedOffer.value.tasaEstimadaAnual,
            cat: selectedOffer.value.cat,
            total_to_pay: selectedOffer.value.total,
            service_fee: selectedOffer.value.cargoServicio ?? 0,
            inputs: {
                vehicle: { ...props.car, brand_name: profile.brand, model_name: profile.model, version_name: profile.version },
                enganche: downPaymentAmount.value,
                plazo: basics.term,
                financiera: selectedOffer.value.nombre,
            },
            offers: offers.value.map((offer) => ({
                nombre: offer.nombre,
                tasaMin: offer.tasa,
                tasaMax: offer.tasa,
                aprobacion: offer.probabilidadAprobacion,
                elegible: offer.califica,
            })),
            reuse_ine: true,
            reuse_domicilio: true,
            reuse_estado_cuenta: true,
        };

        const response = await fetch('/api/credito/solicitudes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message ?? data.errors?.application?.[0] ?? 'No se pudo enviar la solicitud.');
        }

        successFolio.value = data.folio;
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'No se pudo enviar la solicitud.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" @click="emit('close')">
            <div v-if="loginPromptOpen" class="absolute inset-0 z-[130] grid place-items-center bg-secondary/75 p-4 backdrop-blur-sm" @click.stop>
                <div class="w-full max-w-md rounded-3xl border border-border bg-card p-6 text-center shadow-elegant sm:p-8" @click.stop>
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <ShieldCheck class="h-7 w-7" />
                    </span>
                    <h3 class="mt-4 text-xl font-black text-secondary">Inicia sesión para continuar</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Para precalificar tu crédito, guardar tu avance y enviar tu solicitud necesitas una cuenta en WeCar.
                    </p>
                    <div class="mt-6 grid gap-2">
                        <Link href="/login" class="inline-flex h-11 w-full items-center justify-center rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground transition hover:bg-primary/90">
                            Iniciar sesión
                        </Link>
                        <Link href="/registro" class="inline-flex h-11 w-full items-center justify-center rounded-full border border-input bg-background px-5 text-sm font-bold text-secondary transition hover:bg-accent">
                            Crear una cuenta
                        </Link>
                    </div>
                    <button type="button" class="mt-4 text-xs font-semibold text-muted-foreground hover:text-secondary" @click="loginPromptOpen = false; emit('close')">
                        Ahora no, seguir explorando
                    </button>
                </div>
            </div>

            <div class="credit-print-area relative flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-elegant" @click.stop>
                <div class="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <button
                            type="button"
                            aria-label="Volver"
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-border text-secondary transition hover:border-primary/40 hover:text-primary"
                            @click="step > 1 ? step = (step - 1) as Step : emit('close')"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-accent text-accent-foreground">
                            <Banknote class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-black leading-tight text-secondary sm:text-xl">
                                {{ successFolio ? 'Solicitud enviada' : 'Financiar este auto' }}
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ successFolio ? 'Tu expediente fue recibido.' : 'Sigue los pasos para conocer tus opciones y enviar tu solicitud.' }}
                            </p>
                        </div>
                    </div>
                    <button type="button" aria-label="Cerrar" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-accent" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="!successFolio" class="hidden items-center gap-1 border-b border-border px-5 py-3 sm:flex">
                    <template v-for="(label, index) in stepLabels" :key="label">
                        <div class="flex items-center gap-2">
                            <span
                                class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                                :class="step > index + 1 ? 'bg-emerald-500 text-white' : step === index + 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                            >
                                <Check v-if="step > index + 1" class="h-3.5 w-3.5" />
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span class="whitespace-nowrap text-xs font-semibold" :class="step >= index + 1 ? 'text-secondary' : 'text-muted-foreground'">{{ label }}</span>
                        </div>
                        <span v-if="index < stepLabels.length - 1" class="mx-1 h-px flex-1" :class="step > index + 1 ? 'bg-emerald-500' : 'bg-border'" />
                    </template>
                </div>

                <div class="flex-1 overflow-y-auto p-5 sm:p-6">
                    <div v-if="successFolio" class="py-8 text-center">
                        <CheckCircle2 class="mx-auto h-14 w-14 text-emerald-500" />
                        <h3 class="mt-4 text-xl font-black text-secondary">¡Solicitud enviada!</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Un asesor revisará tus documentos y te contactará.
                        </p>
                        <p class="mt-4 inline-flex rounded-full bg-accent px-4 py-2 font-mono text-sm font-bold text-accent-foreground">
                            Folio {{ successFolio }}
                        </p>
                        <Button class="mt-6 w-full" @click="emit('close')">Cerrar</Button>
                    </div>

                    <template v-else>
                        <div class="mb-5 rounded-3xl bg-secondary p-5 text-secondary-foreground shadow-soft">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground/65">Precio del auto</span>
                                        <strong class="text-base font-black">{{ formatPrice(price) }}</strong>
                                    </div>
                                    <input v-model.number="profile.price" type="range" min="50000" max="5000000" step="10000" :disabled="step > 2" class="mt-2 w-full accent-[hsl(var(--primary))] disabled:opacity-60" />
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground/65">Enganche</span>
                                        <strong class="text-base font-black">{{ formatPrice(downPaymentAmount) }} · {{ basics.downPayment }}%</strong>
                                    </div>
                                    <input v-model.number="basics.downPayment" type="range" min="10" max="80" step="5" :disabled="step > 2" class="mt-2 w-full accent-[hsl(var(--primary))] disabled:opacity-60" />
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground/65">Plazo</span>
                                        <strong class="text-base font-black">{{ basics.term }} meses</strong>
                                    </div>
                                    <input v-model.number="basics.term" type="range" min="12" max="72" step="12" :disabled="step > 2" class="mt-2 w-full accent-[hsl(var(--primary))] disabled:opacity-60" />
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 pt-4 text-xs">
                                <span class="text-secondary-foreground/65">Mensualidad estimada <strong class="ml-1 text-base text-white">{{ formatPrice(monthlyEstimate) }}</strong></span>
                                <span class="text-secondary-foreground/65">Monto a financiar <strong class="ml-1 text-white">{{ formatPrice(financedAmount) }}</strong></span>
                                <span class="text-secondary-foreground/65">Tasa desde <strong class="ml-1 text-white">{{ summaryRate }}%</strong></span>
                                <button type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-white/25 px-3 py-1.5 font-bold text-white transition hover:bg-white/10" @click="printQuote">
                                    <FileText class="h-3.5 w-3.5" /> Descargar cotización
                                </button>
                            </div>
                        </div>

                        <!-- Paso 1 -->
                        <div v-if="step === 1" class="grid gap-5">
                            <div class="rounded-2xl border border-border bg-accent/30 p-4">
                                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Vehículo a financiar</p>
                                <p class="mt-1 text-lg font-black text-secondary">{{ props.car?.title || `${profile.brand} ${profile.model} ${profile.year}` }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Ajusta precio, enganche y plazo en el resumen superior.</p>
                            </div>

                            <div>
                                <Label for="phone">Teléfono de contacto</Label>
                                <PhoneInput id="phone" v-model="basics.phone" class="mt-1.5" placeholder="81 1234 5678" />
                                <p class="mt-1 text-[11px] text-muted-foreground">Lo usaremos para dar seguimiento a tu solicitud.</p>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div v-else-if="step === 2" class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <Label for="income">Ingreso mensual neto *</Label>
                                <MoneyInput id="income" v-model="profile.income" data-field="income" :invalid="fieldInvalid('income')" class="mt-1.5" placeholder="Ej. 25,000" />
                                <FormFieldError :message="fieldMessage('income')" />
                            </div>
                            <div>
                                <Label for="tenure">Antigüedad laboral (años) *</Label>
                                <Input id="tenure" v-model.number="profile.tenure" data-field="tenure" :invalid="fieldInvalid('tenure')" type="number" min="0" class="mt-1.5" placeholder="Ej. 3" />
                                <FormFieldError :message="fieldMessage('tenure')" />
                            </div>
                            <div>
                                <Label for="debts">Deudas mensuales</Label>
                                <MoneyInput id="debts" v-model="profile.debts" class="mt-1.5" placeholder="0" />
                            </div>
                            <div>
                                <Label for="age">Edad *</Label>
                                <Input id="age" v-model.number="profile.age" data-field="age" :invalid="fieldInvalid('age')" type="number" min="18" max="99" class="mt-1.5" placeholder="Ej. 35" />
                                <FormFieldError :message="fieldMessage('age')" />
                            </div>
                            <div>
                                <Label for="employment">Tipo de empleo *</Label>
                                <select id="employment" v-model="profile.employment" data-field="employment" :aria-invalid="fieldInvalid('employment') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm" :class="fieldInvalid('employment') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                    <option value="">Selecciona una opción</option>
                                    <option value="fijo">Empleado fijo</option>
                                    <option value="honorarios">Honorarios</option>
                                    <option value="negocio">Negocio propio</option>
                                    <option value="eventual">Eventual</option>
                                </select>
                                <FormFieldError :message="fieldMessage('employment')" />
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="bureau">Historial en Buró / Círculo *</Label>
                                <select id="bureau" v-model="profile.bureau" data-field="bureau" :aria-invalid="fieldInvalid('bureau') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm" :class="fieldInvalid('bureau') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                    <option value="">Selecciona una opción</option>
                                    <option value="excelente">Excelente — sin atrasos</option>
                                    <option value="bueno">Bueno — pagos puntuales</option>
                                    <option value="regular">Regular — algún atraso</option>
                                    <option value="sin-historial">Sin historial</option>
                                    <option value="malo">Malo — cuentas vencidas</option>
                                </select>
                                <FormFieldError :message="fieldMessage('bureau')" />
                            </div>

                            <template v-if="!props.car">
                                <div>
                                    <Label for="brand">Marca</Label>
                                    <select id="brand" v-model="profile.brandId" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm" @change="onBrandChange">
                                        <option value="">Selecciona</option>
                                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="model">Modelo</Label>
                                    <select id="model" v-model="profile.modelId" :disabled="!profile.brandId" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50" @change="onModelChange">
                                        <option value="">{{ profile.brandId ? 'Selecciona el modelo' : 'Selecciona la marca primero' }}</option>
                                        <option v-for="model in modelOptions" :key="model.id" :value="model.id">{{ model.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="year">Año</Label>
                                    <select id="year" v-model.number="profile.year" data-field="year" :aria-invalid="fieldInvalid('year') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm" :class="fieldInvalid('year') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                        <option :value="null">Selecciona el año</option>
                                        <option v-for="year in vehicleYears" :key="year" :value="year">{{ year }}</option>
                                    </select>
                                    <FormFieldError :message="fieldMessage('year')" />
                                </div>
                                <div>
                                    <Label for="price">Precio</Label>
                                    <MoneyInput id="price" v-model="profile.price" data-field="price" :invalid="fieldInvalid('price')" class="mt-1.5" placeholder="Ej. 250,000" />
                                    <FormFieldError :message="fieldMessage('price')" />
                                </div>
                            </template>

                            <div>
                                <Label for="version">Versión (opcional)</Label>
                                <select
                                    id="version"
                                    v-model="profile.version"
                                    :disabled="versionsLoading || versionOptions.length === 0"
                                    class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50"
                                >
                                    <option value="">
                                        {{ versionsLoading ? 'Cargando versiones…' : versionOptions.length ? 'Selecciona la versión' : 'Sin versiones disponibles' }}
                                    </option>
                                    <option v-for="version in versionOptions" :key="version.id" :value="version.name">{{ version.name }}</option>
                                </select>
                                <p v-if="versionsError" class="mt-1 text-[11px] font-semibold text-destructive">{{ versionsError }}</p>
                                <p v-else-if="!versionsLoading && versionOptions.length === 0" class="mt-1 text-[11px] text-muted-foreground">Puedes continuar sin versión; la financiera revisará el vehículo.</p>
                                <button
                                    v-if="!versionsLoading && versionOptions.length === 0 && (profile.modelId || profile.model)"
                                    type="button"
                                    class="mt-1 text-[11px] font-bold text-primary hover:underline"
                                    @click="loadVersions"
                                >
                                    Reintentar
                                </button>
                            </div>
                        </div>

                        <!-- Paso 3 -->
                        <div v-else-if="step === 3" class="space-y-4">
                            <div v-if="evaluating" class="flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4">
                                <Loader2 class="h-5 w-5 animate-spin text-primary" />
                                <p class="text-sm text-secondary">Analizando tu perfil y políticas bancarias…</p>
                            </div>

                            <template v-else>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl bg-gradient-primary p-4 text-primary-foreground">
                                        <p class="text-[10px] font-bold uppercase tracking-wider">Mensualidad desde</p>
                                        <p class="mt-1 text-3xl font-black">{{ formatPrice(eligibleMonthly) }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-gradient-dark p-4 text-white">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Score estimado</p>
                                        <p class="mt-1 text-3xl font-black">{{ evaluation?.scorePerfilEstimado ?? 0 }}</p>
                                        <p class="text-xs text-white/60">Riesgo {{ evaluation?.nivelRiesgo ?? '—' }} · DTI {{ evaluation?.dtiCalculadoPct ?? 0 }}%</p>
                                    </div>
                                </div>

                                <div v-if="evaluation?.resumenEjecutivo" class="rounded-2xl border border-border bg-muted/40 p-4 text-sm text-secondary">
                                    {{ evaluation.resumenEjecutivo }}
                                </div>

                                <div class="space-y-3">
                                    <article
                                        v-for="offer in visibleOffers"
                                        :key="offer.slug"
                                        class="rounded-2xl border p-4 transition"
                                        :class="selectedSlug === offer.slug ? 'border-primary ring-2 ring-primary/20' : 'border-border'"
                                    >
                                        <button type="button" class="w-full text-left" :disabled="!offer.califica" @click="offer.califica && (selectedSlug = offer.slug)">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-black text-secondary">{{ offer.nombre }}</p>
                                                    <p class="text-xs text-muted-foreground">{{ offer.tipo }} · {{ offer.probabilidadAprobacion }}% prob. aprobación</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-lg font-black text-primary">{{ formatPrice(offer.mensualidad) }}/mes</p>
                                                    <p class="text-[11px] text-muted-foreground">Tasa {{ offer.tasaEstimadaAnual }}% · CAT {{ offer.cat }}%</p>
                                                    <p class="text-[11px] text-muted-foreground">Plazo {{ offer.plazoEfectivo ?? basics.term }} meses</p>
                                                </div>
                                            </div>
                                            <p v-if="offer.motivoDictamen" class="mt-2 text-xs text-muted-foreground">{{ offer.motivoDictamen }}</p>
                                            <p v-if="offer.plazoAjustado" class="mt-1 text-xs font-semibold text-amber-600">
                                                El plazo se ajustó al máximo permitido por esta financiera.
                                            </p>
                                            <p class="mt-1 text-xs font-semibold text-secondary">
                                                Total estimado: {{ formatPrice(offer.total ?? 0) }} · Cargo por servicio: {{ formatPrice(offer.cargoServicio ?? 0) }}
                                            </p>
                                            <p v-if="offer.requisitosClave?.length" class="mt-1 text-[11px] text-muted-foreground">
                                                Requisitos: {{ offer.requisitosClave.join(' · ') }}
                                            </p>
                                            <span v-if="!offer.califica" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-destructive">
                                                <AlertCircle class="h-3.5 w-3.5" /> No elegible
                                            </span>
                                        </button>
                                    </article>
                                </div>

                                <div v-if="!showAllOffers && hiddenOffersCount > 0" class="flex justify-center pt-1">
                                    <Button variant="outline" size="sm" @click="showAllOffers = true">
                                        Ver {{ Math.min(3, hiddenOffersCount) }} opciones más
                                    </Button>
                                </div>

                                <div v-if="pawnOffers.length" class="mt-5">
                                    <h3 class="text-sm font-black text-secondary">Préstamo prendario</h3>
                                    <p class="mt-1 text-xs text-muted-foreground">Opciones para obtener liquidez usando tu auto como garantía; no forman parte del crédito de compra.</p>
                                    <div class="mt-3 space-y-2">
                                        <div v-for="offer in pawnOffers" :key="offer.slug" class="rounded-2xl border border-border bg-muted/20 p-3 text-xs">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-bold text-secondary">{{ offer.nombre }}</span>
                                                <span class="font-bold text-primary">{{ formatPrice(offer.mensualidad) }}/mes</span>
                                            </div>
                                            <p class="mt-1 text-muted-foreground">Tasa {{ offer.tasaEstimadaAnual }}% · {{ offer.plazoEfectivo ?? basics.term }} meses</p>
                                            <p v-if="offer.motivoDictamen" class="mt-1 text-muted-foreground">{{ offer.motivoDictamen }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Paso 4: Cuenta -->
                        <div v-else-if="step === 4" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-black text-secondary">Tus datos de cuenta</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Usaremos esta información para integrar tu expediente y contactarte.</p>
                            </div>

                            <div class="rounded-2xl border border-primary/20 bg-accent/30 p-4 text-sm">
                                <p class="font-bold text-secondary">{{ selectedOffer?.nombre }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatPrice(selectedOffer?.mensualidad ?? 0) }}/mes · {{ selectedOffer?.plazoEfectivo ?? basics.term }} meses
                                    · Tasa {{ selectedOffer?.tasaEstimadaAnual ?? 0 }}% · CAT {{ selectedOffer?.cat ?? 0 }}%
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><Label>Nombre completo *</Label><Input v-model="application.full_name" data-field="full_name" :invalid="fieldInvalid('full_name')" class="mt-1.5" /><FormFieldError :message="fieldMessage('full_name')" /></div>
                                <div><Label>Correo *</Label><Input v-model="application.email" data-field="email" :invalid="fieldInvalid('email')" type="email" class="mt-1.5" /><FormFieldError :message="fieldMessage('email')" /></div>
                                <div><Label>Teléfono *</Label><PhoneInput v-model="application.phone" data-field="phone" :invalid="fieldInvalid('phone')" class="mt-1.5" /><FormFieldError :message="fieldMessage('phone')" /></div>
                                <div><Label>Fecha de nacimiento *</Label><Input v-model="application.birthdate" data-field="birthdate" :invalid="fieldInvalid('birthdate')" type="date" class="mt-1.5" /><FormFieldError :message="fieldMessage('birthdate')" /></div>
                                <div><Label>RFC</Label><Input v-model="application.rfc" class="mt-1.5" @input="application.rfc = application.rfc.toUpperCase()" /></div>
                                <div><Label>CURP</Label><Input v-model="application.curp" class="mt-1.5" @input="application.curp = application.curp.toUpperCase()" /></div>
                                <div class="sm:col-span-2"><Label>Calle y número *</Label><Input v-model="application.calle" data-field="calle" :invalid="fieldInvalid('calle')" class="mt-1.5" /><FormFieldError :message="fieldMessage('calle')" /></div>
                                <ZipCodeFields v-model:cp="application.cp" v-model:colonia="application.colonia" v-model:alcaldia="application.alcaldia" v-model:state="application.state" />
                                <div><Label>Ocupación *</Label><Input v-model="application.ocupacion" data-field="ocupacion" :invalid="fieldInvalid('ocupacion')" class="mt-1.5" /><FormFieldError :message="fieldMessage('ocupacion')" /></div>
                                <div><Label>Empresa</Label><Input v-model="application.empresa" class="mt-1.5" /></div>
                                <div><Label>Antigüedad</Label><Input v-model="application.antiguedad" class="mt-1.5" placeholder="3 años" /></div>
                            </div>

                            <p v-if="submitError" class="rounded-xl bg-destructive/10 p-3 text-sm font-semibold text-destructive">{{ submitError }}</p>
                        </div>

                        <!-- Paso 5: Solicitud y documentación -->
                        <div v-else class="space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm">
                                <p class="font-bold text-emerald-800">Financiera elegida: {{ selectedOffer?.nombre }}</p>
                                <button type="button" class="font-bold text-emerald-700 underline underline-offset-2 hover:text-emerald-900" @click="step = 3">
                                    Cambiar
                                </button>
                            </div>

                            <section class="overflow-hidden rounded-3xl border border-border">
                                <button type="button" class="flex w-full items-center justify-between gap-3 bg-card px-5 py-4 text-left" @click="documentsOpen = !documentsOpen">
                                    <div>
                                        <h3 class="text-lg font-black text-secondary">Solicitud y documentación</h3>
                                        <p class="mt-1 text-xs text-muted-foreground">Llena tu solicitud y sube tus documentos en PDF.</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-secondary">{{ documentProgress }}/4</span>
                                        <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform" :class="documentsOpen ? 'rotate-180' : ''" />
                                    </div>
                                </button>

                                <div v-show="documentsOpen" class="space-y-3 border-t border-border bg-background p-4 sm:p-5">
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-primary/40 bg-accent/20 p-4">
                                        <div class="flex items-start gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
                                                <CheckCircle2 v-if="accountCompleted" class="h-5 w-5 text-emerald-600" />
                                                <FileText v-else class="h-5 w-5" />
                                            </span>
                                            <div>
                                                <p class="text-sm font-bold text-secondary">Llenar solicitud</p>
                                                <p class="mt-0.5 text-xs text-muted-foreground">Datos personales, domicilio, laborales y del crédito.</p>
                                            </div>
                                        </div>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10" @click="step = 4">
                                            {{ accountCompleted ? 'Editar' : 'Comenzar' }} <ChevronRight class="h-3.5 w-3.5" />
                                        </button>
                                    </div>

                                    <div v-for="slot in (['ine', 'domicilio', 'estado_cuenta'] as DocSlot[])" :key="slot" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card p-4">
                                        <div class="flex items-start gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full" :class="documents[slot].saved ? 'bg-emerald-500/10 text-emerald-600' : 'bg-primary/10 text-primary'">
                                                <CheckCircle2 v-if="documents[slot].saved" class="h-5 w-5" />
                                                <Upload v-else class="h-5 w-5" />
                                            </span>
                                            <div>
                                                <p class="text-sm font-bold text-secondary">{{ docLabels[slot] }}</p>
                                                <p class="mt-0.5 text-xs text-muted-foreground">{{ documents[slot].name || 'Selecciona un PDF · máx. 5 MB' }}</p>
                                                <p v-if="documents[slot].error" class="mt-1 text-[11px] font-semibold text-destructive">{{ documents[slot].error }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase" :class="documents[slot].saved ? 'bg-emerald-500/10 text-emerald-600' : 'bg-muted text-muted-foreground'">
                                                {{ documents[slot].saved ? 'Listo' : 'Pendiente' }}
                                            </span>
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-primary/40 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10">
                                                <Upload class="h-3.5 w-3.5" />
                                                {{ documents[slot].saved ? 'Cambiar' : 'Subir' }}
                                                <input type="file" accept="application/pdf" class="hidden" @change="uploadDocument(slot, $event)" />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <p class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                <ShieldCheck class="h-4 w-4 text-emerald-500" />
                                Tus documentos se guardan de forma privada y solo los revisa WeCar.
                            </p>

                            <p v-if="submitError" class="rounded-xl bg-destructive/10 p-3 text-sm font-semibold text-destructive">{{ submitError }}</p>
                        </div>

                        <p v-if="submitError && step < 4" class="mt-4 rounded-xl bg-destructive/10 p-3 text-sm font-semibold text-destructive">{{ submitError }}</p>
                    </template>
                </div>

                <div v-if="!successFolio" class="flex items-center justify-between border-t border-border px-5 py-3">
                    <Button variant="ghost" :disabled="step === 1 || evaluating || submitting" @click="step = (step - 1) as Step">
                        <ChevronLeft class="h-4 w-4" /> Atrás
                    </Button>

                    <Button v-if="step === 1" variant="hero" @click="goToProfile">Continuar <ChevronRight class="h-4 w-4" /></Button>
                    <Button v-else-if="step === 2" variant="hero" :disabled="evaluating" @click="evaluate">
                        <Loader2 v-if="evaluating" class="h-4 w-4 animate-spin" />
                        Ver ofertas <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button v-else-if="step === 3" variant="hero" :disabled="!selectedOffer" @click="goToApplication">
                        <Banknote class="h-4 w-4" /> Continuar
                    </Button>
                    <Button v-else-if="step === 4" variant="hero" :disabled="submitting" @click="goToDocuments">
                        Continuar a solicitud <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button v-else variant="hero" :disabled="submitting" @click="submit">
                        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                        Enviar solicitud
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    .credit-print-area,
    .credit-print-area * {
        visibility: visible;
    }

    .credit-print-area {
        position: fixed;
        inset: 0;
        max-height: none !important;
        overflow: visible !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    .credit-print-area button,
    .credit-print-area input[type='range'],
    .credit-print-area input[type='file'] {
        display: none !important;
    }
}
</style>
