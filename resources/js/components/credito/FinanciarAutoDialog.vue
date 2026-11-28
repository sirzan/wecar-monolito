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
    Sparkles,
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
import { useCarVersions } from '@/composables/useCarVersions';
import { clearCreditDraft, loadCreditDraft, saveCreditDraft } from '@/composables/useCreditDraft';
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
        draftScope?: string;
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
        draftScope: 'credito',
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
const savingAccount = ref(false);
const submitError = ref<string | null>(null);
const successFolio = ref<string | null>(null);
const showAllOffers = ref(false);
const accountCompleted = ref(false);
const profileLoadedFromAccount = ref(false);
const editingVehicle = ref(false);
const documentsOpen = ref(true);
const loginPromptOpen = ref(false);
const fieldErrors = ref<Record<string, string>>({});
const isHydrating = ref(false);

const currentRedirectPath = computed(() => {
    if (typeof window === 'undefined') {
        return '/credito';
    }

    return `${window.location.pathname}${window.location.search}`;
});
const loginHref = computed(() => `/login?redirect=${encodeURIComponent(currentRedirectPath.value)}`);
const registerHref = computed(() => `/registro?redirect=${encodeURIComponent(currentRedirectPath.value)}`);

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

const {
    versions: fetchedVersions,
    loading: versionsLoading,
    error: versionsError,
    load: fetchVersions,
    scheduleLoad: scheduleVersionsLoad,
    reset: resetVersions,
} = useCarVersions({ fast: true, debounceMs: 150 });

const versionOptions = computed(() => {
    const list = [...fetchedVersions.value];
    const current = (profile.version || props.car?.version || '').trim();

    if (current && !list.some((version) => version.name === current)) {
        list.unshift({ id: profile.versionId || 'selected-version', name: current });
    }

    return list;
});

const hasPreselectedVehicle = computed(() =>
    Boolean(profile.brand.trim() && profile.model.trim() && profile.year),
);

const vehicleSummaryTitle = computed(() => {
    if (props.car?.title) {
        return props.car.title;
    }

    return [profile.brand, profile.model, profile.version, profile.year]
        .filter((part) => part !== null && part !== undefined && String(part).trim() !== '')
        .join(' ');
});

const loadVersions = async (force = false) => {
    if (!props.open || props.car || !editingVehicle.value) {
        return;
    }

    await fetchVersions({
        brandId: profile.brandId,
        modelId: profile.modelId,
        brand: profile.brand,
        model: profile.model,
        year: profile.year,
        force,
    });
};

watch(
    () => [profile.brandId, profile.modelId, profile.year],
    (value, oldValue) => {
        if (isHydrating.value || !props.open || props.car || !editingVehicle.value) {
            return;
        }

        if (oldValue && (value[0] !== oldValue[0] || value[1] !== oldValue[1] || value[2] !== oldValue[2])) {
            profile.version = '';
            profile.versionId = '';
        }

        scheduleVersionsLoad({
            brandId: profile.brandId,
            modelId: profile.modelId,
            brand: profile.brand,
            model: profile.model,
            year: profile.year,
        });
    },
);

watch(
    () => editingVehicle.value,
    (editing) => {
        if (editing && props.open && !props.car) {
            void loadVersions();
        }
    },
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
const evaluation = ref<any | null>(null);
const selectedSlug = ref<string | null>(null);

const selectedOffer = computed(() => offers.value.find((offer) => offer.slug === selectedSlug.value) ?? null);
const offerInitials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');

const offerTone = (index: number): string =>
    ['bg-blue-600', 'bg-rose-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-600'][index % 5];
const visibleOffers = computed(() => offers.value.slice(0, showAllOffers.value ? 8 : 5));
const hiddenOffersCount = computed(() => Math.max(offers.value.length - visibleOffers.value.length, 0));
const approvedVisibleCount = computed(() => visibleOffers.value.filter((offer) => offer.califica).length);
const recommendedSlug = computed(() => visibleOffers.value.find((offer) => offer.califica)?.slug ?? null);
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
    savingAccount.value = false;
    submitError.value = null;
    successFolio.value = null;
    offers.value = [];
    evaluation.value = null;
    selectedSlug.value = null;
    showAllOffers.value = false;
    accountCompleted.value = false;
    profileLoadedFromAccount.value = false;
    documentsOpen.value = true;
    fieldErrors.value = {};
    documents.ine = {};
    documents.domicilio = {};
    documents.estado_cuenta = {};
    resetVersions();
};

const isAccountDataComplete = computed(() =>
    Boolean(
        application.full_name.trim()
        && application.email.trim()
        && application.phone.trim()
        && application.birthdate.trim()
        && application.calle.trim()
        && application.colonia.trim()
        && application.cp.trim()
        && application.alcaldia.trim()
        && application.ocupacion.trim(),
    ),
);

const persistDraft = (autoReopen = false) => {
    saveCreditDraft(props.draftScope, {
        autoReopen,
        simulator: {
            year: profile.year,
            brand: profile.brand,
            brand_id: profile.brandId,
            model: profile.model,
            model_id: profile.modelId,
            version: profile.version,
            version_id: profile.versionId,
            price: Number(profile.price || 0),
            downPaymentPct: basics.downPayment,
            term: basics.term,
            km: props.car?.km ?? props.initial?.km ?? 0,
            carId: props.car?.id ?? '',
        },
        dialog: {
            step: step.value,
            basics: {
                downPayment: basics.downPayment,
                term: basics.term,
                phone: basics.phone,
            },
            profile: {
                income: profile.income,
                tenure: profile.tenure,
                debts: Number(profile.debts ?? 0),
                age: profile.age,
                employment: profile.employment,
                bureau: profile.bureau,
                brandId: profile.brandId,
                modelId: profile.modelId,
                brand: profile.brand,
                model: profile.model,
                version: profile.version,
                versionId: profile.versionId,
                year: profile.year,
                price: Number(profile.price || 0),
            },
            application: {
                full_name: application.full_name,
                email: application.email,
                phone: application.phone,
                birthdate: application.birthdate,
                rfc: application.rfc,
                curp: application.curp,
                calle: application.calle,
                colonia: application.colonia,
                cp: application.cp,
                alcaldia: application.alcaldia,
                ciudad: application.ciudad,
                state: application.state,
                pais: application.pais,
                ocupacion: application.ocupacion,
                empresa: application.empresa,
                antiguedad: application.antiguedad,
                banco: application.banco,
                tipo_cuenta: application.tipo_cuenta,
            },
        },
    });
};

const restoreDraft = (): boolean => {
    const draft = loadCreditDraft(props.draftScope);

    if (!draft?.dialog) {
        return false;
    }

    const savedBasics = draft.dialog.basics;
    const savedProfile = draft.dialog.profile;
    const savedApp = draft.dialog.application;

    if (savedBasics) {
        if (savedBasics.downPayment >= 10 && savedBasics.downPayment <= 80) {
            basics.downPayment = savedBasics.downPayment;
        }
        if (savedBasics.term >= 12 && savedBasics.term <= 72) {
            basics.term = savedBasics.term;
        }
        if (savedBasics.phone) {
            basics.phone = savedBasics.phone;
        }
    }

    if (savedProfile) {
        if (savedProfile.income !== null && savedProfile.income !== undefined) profile.income = savedProfile.income;
        if (savedProfile.tenure !== null && savedProfile.tenure !== undefined) profile.tenure = savedProfile.tenure;
        if (savedProfile.debts !== null && savedProfile.debts !== undefined) profile.debts = savedProfile.debts;
        if (savedProfile.age !== null && savedProfile.age !== undefined) profile.age = savedProfile.age;
        if (savedProfile.employment) profile.employment = savedProfile.employment;
        if (savedProfile.bureau) profile.bureau = savedProfile.bureau;

        if (!props.car) {
            if (savedProfile.brand) profile.brand = savedProfile.brand;
            if (savedProfile.brandId) profile.brandId = savedProfile.brandId;
            if (savedProfile.model) profile.model = savedProfile.model;
            if (savedProfile.modelId) profile.modelId = savedProfile.modelId;
            if (savedProfile.version) profile.version = savedProfile.version;
            if (savedProfile.versionId) profile.versionId = savedProfile.versionId;
            if (savedProfile.year) profile.year = savedProfile.year;
            if (savedProfile.price > 0) profile.price = savedProfile.price;
        }
    }

    if (savedApp) {
        for (const key of Object.keys(savedApp) as (keyof typeof savedApp)[]) {
            const value = savedApp[key];

            if (typeof value === 'string' && value.trim() !== '') {
                application[key] = value;
            }
        }
    }

    if (draft.autoReopen) {
        saveCreditDraft(props.draftScope, { autoReopen: false });
    }

    return true;
};

const dismissLoginPrompt = () => {
    saveCreditDraft(props.draftScope, { autoReopen: false });
    loginPromptOpen.value = false;
    emit('close');
};

const parseTenureYears = (value: unknown, numericYears: unknown): number | null => {
    if (numericYears !== null && numericYears !== undefined && Number.isFinite(Number(numericYears))) {
        return Math.max(0, Number(numericYears));
    }

    const raw = String(value ?? '').trim().toLowerCase();

    if (!raw) {
        return null;
    }

    const tenureMap: Record<string, number> = {
        'menos de 1 año': 0,
        '1 a 3 años': 2,
        '3 a 5 años': 4,
        'más de 5 años': 6,
    };

    if (raw in tenureMap) {
        return tenureMap[raw];
    }

    const numericMatch = raw.match(/\d+(?:\.\d+)?/);

    return numericMatch ? Number(numericMatch[0]) : null;
};

const loadProfile = async () => {
    if (!user.value) return;

    try {
        const response = await fetch('/api/credito/perfil', { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        const saved = data.profile ?? {};

        const fillIfEmpty = (field: keyof typeof application, value: unknown) => {
            if (typeof application[field] !== 'string') {
                return;
            }

            const current = String(application[field] ?? '').trim();
            const incoming = typeof value === 'string' ? value.trim() : '';

            if (!current && incoming) {
                (application as Record<string, unknown>)[field] = incoming;
            }
        };

        fillIfEmpty('full_name', saved.full_name ?? user.value.name);
        fillIfEmpty('email', saved.email ?? user.value.email);
        fillIfEmpty('phone', saved.phone);
        fillIfEmpty('birthdate', saved.birthdate);
        fillIfEmpty('rfc', saved.rfc);
        fillIfEmpty('curp', saved.curp);
        fillIfEmpty('calle', saved.calle);
        fillIfEmpty('colonia', saved.colonia);
        fillIfEmpty('cp', saved.cp);
        fillIfEmpty('alcaldia', saved.alcaldia ?? saved.ciudad);
        fillIfEmpty('ciudad', saved.ciudad ?? saved.alcaldia);
        fillIfEmpty('state', saved.state);
        fillIfEmpty('pais', saved.pais ?? 'México');
        fillIfEmpty('ocupacion', saved.ocupacion);
        fillIfEmpty('empresa', saved.empresa);
        fillIfEmpty('antiguedad', saved.antiguedad);
        fillIfEmpty('banco', saved.banco);
        fillIfEmpty('tipo_cuenta', saved.tipo_cuenta);

        if (saved.phone && !basics.phone) {
            basics.phone = saved.phone;
        }

        if (profile.income === null && saved.ingreso_mensual !== null && saved.ingreso_mensual !== undefined && Number(saved.ingreso_mensual) > 0) {
            profile.income = Number(saved.ingreso_mensual);
        }

        if (profile.debts === null && saved.deuda_mensual !== null && saved.deuda_mensual !== undefined) {
            profile.debts = Number(saved.deuda_mensual);
        }

        if (profile.age === null && saved.birthdate) {
            const birthdate = new Date(saved.birthdate);

            if (!Number.isNaN(birthdate.getTime())) {
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();

                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }

                if (age >= 18 && age <= 99) {
                    profile.age = age;
                }
            }
        }

        const employmentMap: Record<string, string> = {
            fijo: 'fijo',
            formal: 'fijo',
            independiente: 'honorarios',
            honorarios: 'honorarios',
            negocio: 'negocio',
            'negocio propio': 'negocio',
            eventual: 'eventual',
        };
        const savedEmployment = String(saved.tipo_trabajo ?? '').trim().toLowerCase();

        if (savedEmployment && employmentMap[savedEmployment] && !profile.employment) {
            profile.employment = employmentMap[savedEmployment];
        }

        if (profile.tenure === null) {
            const parsedTenure = parseTenureYears(saved.antiguedad, saved.antiguedad_anios);

            if (parsedTenure !== null) {
                profile.tenure = parsedTenure;
            }
        }

        const hasSavedAccountData = Boolean(
            saved.calle || saved.colonia || saved.cp || saved.alcaldia || saved.ocupacion || saved.birthdate || saved.rfc || saved.curp || saved.phone,
        );
        profileLoadedFromAccount.value = hasSavedAccountData;

        if (isAccountDataComplete.value) {
            accountCompleted.value = true;
        }
    } catch {
        // El usuario puede completar sus datos manualmente.
    }
};

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }

        isHydrating.value = true;
        reset();
        syncFromInitial();
        restoreDraft();

        if (!application.full_name && user.value?.name) {
            application.full_name = user.value.name;
        }
        if (!application.email && user.value?.email) {
            application.email = user.value.email;
        }
        if (!application.phone && basics.phone) {
            application.phone = basics.phone;
        }

        editingVehicle.value = !props.car && !hasPreselectedVehicle.value;
        loginPromptOpen.value = !user.value;

        if (!user.value) {
            persistDraft(true);
            isHydrating.value = false;
            return;
        }

        void loadSavedDocuments();
        await loadProfile();
        step.value = 2;
        isHydrating.value = false;

        if (editingVehicle.value && !props.car) {
            void loadVersions();
        }
    },
    { immediate: true },
);

watch(
    () => basics.phone,
    (value) => {
        if (value && value.trim() !== '') {
            application.phone = value;
        }
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
    resolveModelName();

    const errors: Record<string, string> = {};

    if (profile.income === null || profile.income <= 0) errors.income = 'Ingresa tu ingreso mensual.';
    if (profile.age === null || profile.age < 18 || profile.age > 99) errors.age = 'Ingresa una edad válida (18 a 99 años).';
    if (!profile.employment) errors.employment = 'Selecciona tu tipo de empleo.';
    if (!profile.bureau) errors.bureau = 'Selecciona tu historial crediticio.';
    if (profile.tenure === null || profile.tenure < 0) errors.tenure = 'Ingresa tu antigüedad laboral.';
    if (!profile.brand.trim()) errors.brand = 'Selecciona o ingresa la marca del vehículo.';
    if (!profile.model.trim()) errors.model = 'Selecciona o ingresa el modelo del vehículo.';
    if (!profile.year) errors.year = 'Selecciona el año del vehículo.';
    if (!profile.price) errors.price = 'Ingresa el precio del vehículo.';

    if (errors.brand || errors.model || errors.year || errors.price) {
        if (!props.car) {
            editingVehicle.value = true;
        }
    }

    const labels: Record<string, string> = {
        income: 'ingreso mensual', age: 'edad', employment: 'tipo de empleo', bureau: 'historial crediticio',
        tenure: 'antigüedad laboral', brand: 'marca del vehículo', model: 'modelo del vehículo',
        year: 'año del vehículo', price: 'precio del vehículo',
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
        selectedSlug.value = offers.value.find((offer) => offer.califica)?.slug ?? null;
        showAllOffers.value = false;
        accountCompleted.value = isAccountDataComplete.value;
        persistDraft(false);
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
        persistDraft(true);
        loginPromptOpen.value = true;
        return;
    }

    submitError.value = null;

    if (isAccountDataComplete.value) {
        accountCompleted.value = true;
        documentsOpen.value = true;
        step.value = 5;
        return;
    }

    accountCompleted.value = false;
    step.value = 4;
};

const goToDocuments = async () => {
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
    savingAccount.value = true;

    try {
        await fetch('/api/credito/perfil', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                full_name: application.full_name,
                phone: application.phone,
                birthdate: application.birthdate,
                rfc: application.rfc || null,
                curp: application.curp || null,
                calle: application.calle,
                colonia: application.colonia,
                cp: application.cp,
                alcaldia: application.alcaldia,
                ciudad: application.ciudad || application.alcaldia,
                state: application.state || null,
                pais: application.pais || 'México',
                ocupacion: application.ocupacion,
                empresa: application.empresa || null,
                antiguedad: application.antiguedad || (profile.tenure !== null ? `${profile.tenure} años` : null),
                antiguedad_anios: profile.tenure,
                tipo_trabajo: profile.employment || null,
                ingreso_mensual: profile.income,
                deuda_mensual: profile.debts ?? 0,
                banco: application.banco || null,
                tipo_cuenta: application.tipo_cuenta || null,
            }),
        });
        profileLoadedFromAccount.value = true;
    } catch {
        // Si falla el guardado en segundo plano, igual enviamos los datos con la solicitud.
    } finally {
        savingAccount.value = false;
    }

    accountCompleted.value = true;
    documentsOpen.value = true;
    persistDraft(false);
    step.value = 5;
};

const printQuote = () => {
    window.print();
};

const submit = async () => {
    if (!selectedOffer.value) return;

    if (!user.value) {
        persistDraft(true);
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
            ciudad: application.ciudad || application.alcaldia,
            pais: application.pais || 'México',
            phone: application.phone || basics.phone,
            ingreso_mensual: Number(profile.income),
            deuda_mensual: Number(profile.debts ?? 0),
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
        clearCreditDraft(props.draftScope);
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
                        <Link
                            :href="loginHref"
                            class="inline-flex h-11 w-full items-center justify-center rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                            @click="persistDraft(true)"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            :href="registerHref"
                            class="inline-flex h-11 w-full items-center justify-center rounded-full border border-input bg-background px-5 text-sm font-bold text-secondary transition hover:bg-accent"
                            @click="persistDraft(true)"
                        >
                            Crear una cuenta
                        </Link>
                    </div>
                    <button type="button" class="mt-4 text-xs font-semibold text-muted-foreground hover:text-secondary" @click="dismissLoginPrompt">
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
                                    <input v-model.number="profile.price" type="range" min="50000" max="5000000" step="10000" :disabled="step > 2" class="range-brand mt-2 w-full disabled:opacity-60" />
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground/65">Enganche</span>
                                        <strong class="text-base font-black">{{ formatPrice(downPaymentAmount) }} · {{ basics.downPayment }}%</strong>
                                    </div>
                                    <input v-model.number="basics.downPayment" type="range" min="10" max="80" step="5" :disabled="step > 2" class="range-brand mt-2 w-full disabled:opacity-60" />
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-secondary-foreground/65">Plazo</span>
                                        <strong class="text-base font-black">{{ basics.term }} meses</strong>
                                    </div>
                                    <input v-model.number="basics.term" type="range" min="12" max="72" step="12" :disabled="step > 2" class="range-brand mt-2 w-full disabled:opacity-60" />
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
                                <p class="mt-1 text-[11px] text-muted-foreground">Ejemplo: $25,000 MXN netos al mes.</p>
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
                                <select id="employment" v-model="profile.employment" data-field="employment" :aria-invalid="fieldInvalid('employment') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" :class="fieldInvalid('employment') ? 'border-destructive ring-2 ring-destructive/20' : ''">
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
                                <select id="bureau" v-model="profile.bureau" data-field="bureau" :aria-invalid="fieldInvalid('bureau') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" :class="fieldInvalid('bureau') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                    <option value="">Selecciona una opción</option>
                                    <option value="excelente">Excelente — sin atrasos</option>
                                    <option value="bueno">Bueno — pagos puntuales</option>
                                    <option value="regular">Regular — algún atraso</option>
                                    <option value="sin-historial">Sin historial</option>
                                    <option value="malo">Malo — cuentas vencidas</option>
                                </select>
                                <FormFieldError :message="fieldMessage('bureau')" />
                            </div>

                            <div class="sm:col-span-2 rounded-2xl border border-border bg-accent/30 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Vehículo seleccionado</p>
                                        <p class="mt-0.5 truncate text-sm font-black text-secondary">
                                            {{ vehicleSummaryTitle || 'Completa los datos del vehículo' }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="!props.car && hasPreselectedVehicle"
                                        type="button"
                                        class="text-xs font-bold text-primary hover:underline"
                                        @click="editingVehicle = !editingVehicle"
                                    >
                                        {{ editingVehicle ? 'Ocultar vehículo' : 'Cambiar vehículo' }}
                                    </button>
                                </div>
                            </div>

                            <template v-if="props.car && (!profile.brand.trim() || !profile.model.trim() || !profile.year)">
                                <div v-if="!profile.brand.trim()">
                                    <Label for="external-brand">Marca *</Label>
                                    <Input id="external-brand" v-model="profile.brand" data-field="brand" :invalid="fieldInvalid('brand')" class="mt-1.5" placeholder="Ej. Ford" />
                                    <FormFieldError :message="fieldMessage('brand')" />
                                </div>
                                <div v-if="!profile.model.trim()">
                                    <Label for="external-model">Modelo *</Label>
                                    <Input id="external-model" v-model="profile.model" data-field="model" :invalid="fieldInvalid('model')" class="mt-1.5" placeholder="Ej. Fiesta" />
                                    <FormFieldError :message="fieldMessage('model')" />
                                </div>
                                <div v-if="!profile.year">
                                    <Label for="external-year">Año *</Label>
                                    <select id="external-year" v-model.number="profile.year" data-field="year" :aria-invalid="fieldInvalid('year') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" :class="fieldInvalid('year') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                        <option :value="null">Selecciona el año</option>
                                        <option v-for="year in vehicleYears" :key="year" :value="year">{{ year }}</option>
                                    </select>
                                    <FormFieldError :message="fieldMessage('year')" />
                                </div>
                            </template>

                            <template v-if="!props.car && editingVehicle">
                                <div>
                                    <Label for="brand">Marca *</Label>
                                    <select id="brand" v-model="profile.brandId" data-field="brand" :aria-invalid="fieldInvalid('brand') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" :class="fieldInvalid('brand') ? 'border-destructive ring-2 ring-destructive/20' : ''" @change="onBrandChange">
                                        <option value="">Selecciona</option>
                                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                    </select>
                                    <FormFieldError :message="fieldMessage('brand')" />
                                </div>
                                <div>
                                    <Label for="model">Modelo *</Label>
                                    <select id="model" v-model="profile.modelId" data-field="model" :disabled="!profile.brandId" :aria-invalid="fieldInvalid('model') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm disabled:opacity-50" :class="fieldInvalid('model') ? 'border-destructive ring-2 ring-destructive/20' : ''" @change="onModelChange">
                                        <option value="">{{ profile.brandId ? 'Selecciona el modelo' : 'Selecciona la marca primero' }}</option>
                                        <option v-for="model in modelOptions" :key="model.id" :value="model.id">{{ model.name }}</option>
                                    </select>
                                    <FormFieldError :message="fieldMessage('model')" />
                                </div>
                                <div>
                                    <Label for="year">Año *</Label>
                                    <select id="year" v-model.number="profile.year" data-field="year" :aria-invalid="fieldInvalid('year') || undefined" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm" :class="fieldInvalid('year') ? 'border-destructive ring-2 ring-destructive/20' : ''">
                                        <option :value="null">Selecciona el año</option>
                                        <option v-for="year in vehicleYears" :key="year" :value="year">{{ year }}</option>
                                    </select>
                                    <FormFieldError :message="fieldMessage('year')" />
                                </div>
                                <div>
                                    <Label for="price">Precio *</Label>
                                    <MoneyInput id="price" v-model="profile.price" data-field="price" :invalid="fieldInvalid('price')" class="mt-1.5" placeholder="Ej. 250,000" />
                                    <FormFieldError :message="fieldMessage('price')" />
                                </div>
                                <div class="sm:col-span-2">
                                    <Label for="version">Versión (opcional)</Label>
                                    <select
                                        id="version"
                                        v-model="profile.version"
                                        :disabled="!profile.modelId || !profile.year || (versionsLoading && versionOptions.length === 0)"
                                        class="mt-1.5 h-10 w-full rounded-md border border-input bg-background field-select pl-3 pr-10 text-sm disabled:opacity-50"
                                    >
                                        <option value="">
                                            {{ versionsLoading ? 'Cargando versiones…' : versionOptions.length ? 'Sin especificar (opcional)' : 'Sin versiones (opcional)' }}
                                        </option>
                                        <option v-for="version in versionOptions" :key="version.id" :value="version.name">{{ version.name }}</option>
                                    </select>
                                    <p v-if="versionsError" class="mt-1 text-[11px] font-semibold text-destructive">{{ versionsError }}</p>
                                    <p v-else-if="!versionsLoading && versionOptions.length === 0" class="mt-1 text-[11px] text-muted-foreground">Campo opcional: puedes continuar sin seleccionar versión.</p>
                                    <button
                                        v-if="!versionsLoading && versionOptions.length === 0 && (profile.modelId || profile.model) && profile.year"
                                        type="button"
                                        class="mt-1 text-[11px] font-bold text-primary hover:underline"
                                        @click="loadVersions(true)"
                                    >
                                        Reintentar
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Paso 3 -->
                        <div v-else-if="step === 3" class="space-y-5">
                            <div v-if="evaluating" class="flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4">
                                <Loader2 class="h-5 w-5 animate-spin text-primary" />
                                <p class="text-sm text-secondary">Analizando tu perfil y políticas bancarias…</p>
                            </div>

                            <template v-else>
                                <div class="rounded-3xl border border-primary/20 bg-gradient-to-r from-accent/80 via-card to-accent/40 p-5 sm:p-6">
                                    <p class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-primary">
                                        <Sparkles class="h-4 w-4" /> Precalificación con IA
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">Forma rápida para darte opciones reales.</p>
                                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Monto a financiar</p>
                                            <p class="mt-1 text-lg font-black text-secondary sm:text-xl">{{ formatPrice(financedAmount) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Plazo</p>
                                            <p class="mt-1 text-lg font-black text-secondary sm:text-xl">{{ basics.term }} meses</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Financieras que aprueban</p>
                                            <p class="mt-1 text-lg font-black text-secondary sm:text-xl">{{ approvedVisibleCount }} de {{ visibleOffers.length }}</p>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-sm text-muted-foreground">
                                    Ordenadas por mejor match con tu perfil. Selecciona la financiera con la que quieres continuar.
                                </p>

                                <div class="space-y-3">
                                    <article
                                        v-for="(offer, index) in visibleOffers"
                                        :key="offer.slug"
                                        class="rounded-3xl border bg-card p-4 shadow-soft transition sm:p-5"
                                        :class="selectedSlug === offer.slug ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-primary/40'"
                                    >
                                        <button type="button" class="w-full text-left" :disabled="!offer.califica" @click="offer.califica && (selectedSlug = offer.slug)">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full text-sm font-black text-white shadow-soft" :class="offerTone(index)">
                                                        <img v-if="offer.logo" :src="offer.logo" :alt="offer.nombre" class="h-full w-full object-cover" />
                                                        <template v-else>{{ offerInitials(offer.nombre) }}</template>
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="font-black text-secondary">{{ offer.nombre }}</p>
                                                        <p class="mt-0.5 text-xs capitalize text-muted-foreground">{{ offer.tipo }}</p>
                                                    </div>
                                                </div>
                                                <span v-if="recommendedSlug === offer.slug" class="inline-flex items-center gap-1 rounded-full bg-primary/15 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-primary">
                                                    <Sparkles class="h-3 w-3" /> Recomendada · Mejor match
                                                </span>
                                            </div>

                                            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-border/70 pt-4 sm:grid-cols-4">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Mensualidad</p>
                                                    <p class="mt-1 text-lg font-black text-primary">{{ formatPrice(offer.mensualidad) }}<span class="text-xs font-semibold text-muted-foreground">/mes</span></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Tasa anual</p>
                                                    <p class="mt-1 text-base font-black text-secondary">{{ offer.tasaEstimadaAnual }}%</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">CAT</p>
                                                    <p class="mt-1 text-base font-black text-secondary">{{ offer.cat }}%</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Total a pagar</p>
                                                    <p class="mt-1 text-base font-black text-secondary">{{ formatPrice(offer.total ?? 0) }}</p>
                                                </div>
                                            </div>

                                            <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                                                <div class="text-xs text-muted-foreground">
                                                    <p v-if="offer.plazoEfectivo">Plazo {{ offer.plazoEfectivo }} meses</p>
                                                    <p v-if="offer.plazoAjustado" class="font-semibold text-amber-600">El plazo se ajustó al máximo permitido.</p>
                                                    <p v-if="offer.motivoDictamen" class="mt-1">{{ offer.motivoDictamen }}</p>
                                                    <p v-if="offer.requisitosClave?.length" class="mt-1 text-[11px]">{{ offer.requisitosClave.join(' · ') }}</p>
                                                </div>
                                                <div class="w-full max-w-[230px]">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700">
                                                        ↗ {{ offer.probabilidadAprobacion }}% prob. aprobación
                                                    </span>
                                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                                        <div class="h-full rounded-full bg-gradient-to-r from-primary to-orange-500" :style="{ width: `${Math.max(0, Math.min(100, Number(offer.probabilidadAprobacion) || 0))}%` }" />
                                                    </div>
                                                </div>
                                            </div>

                                            <span v-if="!offer.califica" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-destructive">
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
                            </template>
                        </div>

                        <!-- Paso 4: Cuenta -->
                        <div v-else-if="step === 4" class="space-y-5">
                            <div>
                                <h3 class="text-lg font-black text-secondary">Tus datos de cuenta</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ profileLoadedFromAccount ? 'Precargamos la información guardada en tu cuenta. Solo completa o ajusta lo que falte y la guardaremos automáticamente.' : 'Usaremos esta información para integrar tu expediente y la guardaremos en tu cuenta.' }}
                                </p>
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
                                                <p class="text-sm font-bold text-secondary">
                                                    {{ accountCompleted ? 'Datos de cuenta listos' : 'Llenar solicitud' }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-muted-foreground">
                                                    {{ accountCompleted
                                                        ? `${application.full_name} · ${application.phone} · ${application.calle}, ${application.colonia}`
                                                        : 'Datos personales, domicilio, laborales y del crédito.' }}
                                                </p>
                                            </div>
                                        </div>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10" @click="step = 4">
                                            {{ accountCompleted ? 'Revisar / editar' : 'Comenzar' }} <ChevronRight class="h-3.5 w-3.5" />
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
                    <Button v-else-if="step === 4" variant="hero" :disabled="submitting || savingAccount" @click="goToDocuments">
                        <Loader2 v-if="savingAccount" class="h-4 w-4 animate-spin" />
                        {{ savingAccount ? 'Guardando…' : 'Guardar y continuar' }} <ChevronRight class="h-4 w-4" />
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
