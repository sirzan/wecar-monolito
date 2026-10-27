<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FileText,
    Loader2,
    ShieldCheck,
    Upload,
    X,
} from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { cn } from '@/lib/utils';

interface Brand {
    id: string;
    name: string;
    models: { id: string; name: string }[];
}

interface CarInput {
    id?: string;
    brand?: string | null;
    model?: string | null;
    version?: string | null;
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
            model?: string;
            version?: string;
            year?: number;
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

type Step = 1 | 2 | 3 | 4;
const step = ref<Step>(1);
const evaluating = ref(false);
const submitting = ref(false);
const submitError = ref<string | null>(null);
const successFolio = ref<string | null>(null);

const basics = reactive({
    phone: '',
    downPayment: props.initial.downPayment ?? 20,
    term: props.initial.term ?? 48,
});

const profile = reactive({
    income: 0,
    tenure: 1,
    bureau: 'bueno',
    employment: 'fijo',
    debts: 0,
    age: 30,
    brand: props.car?.brand ?? props.initial.brand ?? '',
    model: props.car?.model ?? props.initial.model ?? '',
    year: props.car?.year ?? props.initial.year ?? new Date().getFullYear() - 3,
    price: props.car?.price ?? props.initial.price ?? 250000,
    version: props.car?.version ?? props.initial.version ?? '',
});

const versionOptions = ref<{ id: string; name: string }[]>([]);
const versionsLoading = ref(false);
const versionsError = ref('');
let versionsTimer: ReturnType<typeof setTimeout> | null = null;

const loadVersions = async () => {
    if (!profile.brand || !profile.model || !profile.year) {
        versionOptions.value = [];
        return;
    }

    versionsLoading.value = true;
    versionsError.value = '';

    try {
        const params = new URLSearchParams({
            brand: profile.brand,
            model: profile.model,
            year: String(profile.year),
        });
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
    () => [profile.brand, profile.model, profile.year],
    (value, oldValue) => {
        if (oldValue && (value[0] !== oldValue[0] || value[1] !== oldValue[1] || value[2] !== oldValue[2])) {
            profile.version = props.car?.version ?? '';
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
    documents.ine = {};
    documents.domicilio = {};
    documents.estado_cuenta = {};
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
            application.full_name = user.value?.name ?? '';
            application.email = user.value?.email ?? '';
            application.phone = basics.phone;
            void loadSavedDocuments();
        }
    },
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
    if (!profile.version) {
        submitError.value = 'Selecciona la versión del auto.';
        return;
    }

    if (!props.car && versionOptions.value.length === 0) {
        submitError.value = 'No hay versiones disponibles para este auto. Intenta de nuevo más tarde.';
        return;
    }

    if (!profile.income || profile.income <= 0) {
        submitError.value = 'Ingresa tu ingreso mensual.';
        return;
    }

    submitError.value = null;
    evaluating.value = true;

    try {
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
                    version: profile.version,
                    anio: Number(profile.year),
                    precio: price.value,
                    km: props.car?.km ?? 0,
                    tipo: props.car?.condition === 'new' ? 'nuevo' : 'seminuevo',
                },
                perfil: {
                    ingresoMensual: Number(profile.income),
                    enganche: downPaymentAmount.value,
                    plazoMeses: basics.term,
                    historialBuro: profile.bureau,
                    tipoEmpleo: profile.employment,
                    antiguedadLaboralAnios: Number(profile.tenure),
                    deudasMensuales: Number(profile.debts),
                    edad: Number(profile.age),
                },
            }),
        });

        if (!response.ok) throw new Error('No se pudo evaluar tu perfil.');

        evaluation.value = await response.json();
        offers.value = (evaluation.value.opcionesRecomendadas ?? []).map(normalizeOffer);
        pawnOffers.value = (evaluation.value.opcionesPrendarias ?? []).map(normalizeOffer);
        selectedSlug.value = offers.value.find((offer) => offer.califica)?.slug ?? null;
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
        submitError.value = 'Inicia sesión para enviar tu solicitud.';
        return;
    }

    submitError.value = null;
    step.value = 4;
};

const submit = async () => {
    if (!selectedOffer.value || !user.value) return;

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
            phone: basics.phone || application.phone,
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
            <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-elegant" @click.stop>
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-primary">Crédito WeCar</p>
                        <h2 class="text-lg font-black text-secondary">
                            {{ successFolio ? 'Solicitud enviada' : ['Datos', 'Precalificación', 'Ofertas', 'Solicitud'][step - 1] }}
                        </h2>
                    </div>
                    <button type="button" aria-label="Cerrar" class="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-accent" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="!successFolio" class="flex items-center gap-2 border-b border-border px-5 py-3">
                    <div v-for="n in 4" :key="n" class="flex flex-1 items-center gap-2">
                        <span
                            class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                            :class="step >= n ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                        >
                            <Check v-if="step > n" class="h-3.5 w-3.5" />
                            <template v-else>{{ n }}</template>
                        </span>
                        <span v-if="n < 4" class="h-px flex-1" :class="step > n ? 'bg-primary' : 'bg-border'" />
                    </div>
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
                        <!-- Paso 1 -->
                        <div v-if="step === 1" class="grid gap-5">
                            <div class="rounded-2xl bg-accent/40 p-4">
                                <p class="text-sm font-bold text-secondary">{{ props.car?.title || `${profile.brand} ${profile.model} ${profile.year}` }}</p>
                                <p class="mt-1 text-2xl font-black text-primary">{{ formatPrice(price) }}</p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <Label>Enganche</Label>
                                    <span class="font-bold text-secondary">{{ basics.downPayment }}% · {{ formatPrice(downPaymentAmount) }}</span>
                                </div>
                                <input v-model.number="basics.downPayment" type="range" min="10" max="80" step="5" class="mt-2 w-full accent-[hsl(var(--primary))]" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <Label>Plazo</Label>
                                    <span class="font-bold text-secondary">{{ basics.term }} meses</span>
                                </div>
                                <input v-model.number="basics.term" type="range" min="12" max="72" step="12" class="mt-2 w-full accent-[hsl(var(--primary))]" />
                            </div>

                            <div>
                                <Label for="phone">Teléfono</Label>
                                <Input id="phone" v-model="basics.phone" type="tel" class="mt-1.5" placeholder="81 1234 5678" />
                            </div>

                            <div class="rounded-2xl bg-secondary p-4 text-secondary-foreground">
                                <p class="text-[11px] font-bold text-primary">Mensualidad estimada</p>
                                <p class="mt-1 text-3xl font-black">{{ formatPrice(monthlyEstimate) }}<span class="text-xs font-semibold text-secondary-foreground/60">/mes</span></p>
                                <p class="mt-2 text-xs text-secondary-foreground/60">Monto a financiar: {{ formatPrice(financedAmount) }}</p>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div v-else-if="step === 2" class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <Label for="income">Ingreso mensual neto</Label>
                                <Input id="income" v-model.number="profile.income" type="number" min="1" class="mt-1.5" placeholder="25000" />
                            </div>
                            <div>
                                <Label for="tenure">Antigüedad laboral (años)</Label>
                                <Input id="tenure" v-model.number="profile.tenure" type="number" min="0" class="mt-1.5" />
                            </div>
                            <div>
                                <Label for="debts">Deudas mensuales</Label>
                                <Input id="debts" v-model.number="profile.debts" type="number" min="0" class="mt-1.5" />
                            </div>
                            <div>
                                <Label for="age">Edad</Label>
                                <Input id="age" v-model.number="profile.age" type="number" min="18" max="99" class="mt-1.5" />
                            </div>
                            <div>
                                <Label for="employment">Tipo de empleo</Label>
                                <select id="employment" v-model="profile.employment" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="fijo">Empleado fijo</option>
                                    <option value="honorarios">Honorarios</option>
                                    <option value="negocio">Negocio propio</option>
                                    <option value="eventual">Eventual</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <Label for="bureau">Historial en Buró / Círculo</Label>
                                <select id="bureau" v-model="profile.bureau" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="excelente">Excelente — sin atrasos</option>
                                    <option value="bueno">Bueno — pagos puntuales</option>
                                    <option value="regular">Regular — algún atraso</option>
                                    <option value="sin-historial">Sin historial</option>
                                    <option value="malo">Malo — cuentas vencidas</option>
                                </select>
                            </div>

                            <template v-if="!props.car">
                                <div>
                                    <Label for="brand">Marca</Label>
                                    <select id="brand" v-model="profile.brand" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                        <option value="">Selecciona</option>
                                        <option v-for="brand in brands" :key="brand.id" :value="brand.name">{{ brand.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="model">Modelo</Label>
                                    <Input id="model" v-model="profile.model" class="mt-1.5" />
                                </div>
                                <div>
                                    <Label for="year">Año</Label>
                                    <Input id="year" v-model.number="profile.year" type="number" min="1990" :max="new Date().getFullYear() + 1" class="mt-1.5" />
                                </div>
                                <div>
                                    <Label for="price">Precio</Label>
                                    <Input id="price" v-model.number="profile.price" type="number" min="1" class="mt-1.5" />
                                </div>
                            </template>

                            <div>
                                <Label for="version">Versión *</Label>
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
                                <button
                                    v-if="!versionsLoading && versionOptions.length === 0 && profile.model"
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
                                        v-for="offer in offers"
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

                        <!-- Paso 4 -->
                        <div v-else class="space-y-4">
                            <div class="rounded-2xl bg-accent/40 p-4">
                                <p class="text-sm font-bold text-secondary">{{ selectedOffer?.nombre }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatPrice(selectedOffer?.mensualidad ?? 0) }}/mes · {{ selectedOffer?.plazoEfectivo ?? basics.term }} meses
                                    · Tasa {{ selectedOffer?.tasaEstimadaAnual ?? 0 }}% · CAT {{ selectedOffer?.cat ?? 0 }}%
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Total estimado {{ formatPrice(selectedOffer?.total ?? 0) }} · Cargo por servicio {{ formatPrice(selectedOffer?.cargoServicio ?? 0) }}
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><Label>Nombre completo</Label><Input v-model="application.full_name" class="mt-1.5" /></div>
                                <div><Label>Correo</Label><Input v-model="application.email" type="email" class="mt-1.5" /></div>
                                <div><Label>Teléfono</Label><Input v-model="application.phone" type="tel" class="mt-1.5" /></div>
                                <div><Label>Fecha de nacimiento</Label><Input v-model="application.birthdate" type="date" class="mt-1.5" /></div>
                                <div><Label>RFC</Label><Input v-model="application.rfc" class="mt-1.5" /></div>
                                <div><Label>CURP</Label><Input v-model="application.curp" class="mt-1.5" /></div>
                                <div class="sm:col-span-2"><Label>Calle y número</Label><Input v-model="application.calle" class="mt-1.5" /></div>
                                <div><Label>Colonia</Label><Input v-model="application.colonia" class="mt-1.5" /></div>
                                <div><Label>Código postal</Label><Input v-model="application.cp" class="mt-1.5" /></div>
                                <div><Label>Alcaldía / municipio</Label><Input v-model="application.alcaldia" class="mt-1.5" /></div>
                                <div><Label>Ciudad</Label><Input v-model="application.ciudad" class="mt-1.5" /></div>
                                <div><Label>Ocupación</Label><Input v-model="application.ocupacion" class="mt-1.5" /></div>
                                <div><Label>Empresa</Label><Input v-model="application.empresa" class="mt-1.5" /></div>
                                <div><Label>Antigüedad</Label><Input v-model="application.antiguedad" class="mt-1.5" placeholder="3 años" /></div>
                                <div><Label>Banco</Label><Input v-model="application.banco" class="mt-1.5" /></div>
                                <div><Label>Tipo de cuenta</Label><Input v-model="application.tipo_cuenta" class="mt-1.5" placeholder="Nómina" /></div>
                            </div>

                            <div class="space-y-3">
                                <p class="text-sm font-bold text-secondary">Documentos</p>
                                <div v-for="slot in (['ine', 'domicilio', 'estado_cuenta'] as DocSlot[])" :key="slot" class="rounded-xl border border-border p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-primary" />
                                            <div>
                                                <p class="text-sm font-semibold text-secondary">{{ docLabels[slot] }}</p>
                                                <p class="text-[11px] text-muted-foreground">{{ documents[slot].name || 'PDF · máx. 5 MB' }}</p>
                                            </div>
                                        </div>
                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-primary/40 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10">
                                            <Upload class="h-3.5 w-3.5" />
                                            {{ documents[slot].saved ? 'Cambiar' : 'Subir' }}
                                            <input type="file" accept="application/pdf" class="hidden" @change="uploadDocument(slot, $event)" />
                                        </label>
                                    </div>
                                    <p v-if="documents[slot].saved" class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600">
                                        <CheckCircle2 class="h-3.5 w-3.5" /> Documento listo
                                    </p>
                                    <p v-if="documents[slot].error" class="mt-2 text-[11px] font-semibold text-destructive">{{ documents[slot].error }}</p>
                                </div>
                            </div>

                            <p class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                <ShieldCheck class="h-4 w-4 text-emerald-500" />
                                Tus documentos se guardan de forma privada y solo los revisa WeCar.
                            </p>
                        </div>

                        <p v-if="submitError" class="mt-4 rounded-xl bg-destructive/10 p-3 text-sm font-semibold text-destructive">{{ submitError }}</p>
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
                        <Banknote class="h-4 w-4" /> Iniciar trámite
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
