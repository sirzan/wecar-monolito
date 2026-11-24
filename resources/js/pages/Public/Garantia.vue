<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    Car,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    Download,
    FileCheck2,
    Handshake,
    Headphones,
    HelpCircle,
    Info,
    LoaderCircle,
    Percent,
    Search,
    ShieldCheck,
    Sparkles,
    Wallet,
    Wrench,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { faqCategories, faqs } from '@/lib/faqs';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    settings?: { title?: string; description?: string; enabled?: boolean; contact_email?: string };
    quote?: {
        service_fee_rate: number;
        valid_days: number;
        price_min: number;
        price_max: number;
        contact_email: string;
    };
    profile?: { name: string; email: string; phone: string | null } | null;
    seo: { title: string; description: string; canonical: string };
}>();

const page = usePage<{ auth: { user: { name: string; email: string } | null } }>();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));

const includes = [
    {
        icon: Wrench,
        title: 'Validación mecánica de 160 puntos',
        description: 'Diagnóstico por técnicos certificados en motor, transmisión, frenos, suspensión y sistemas electrónicos.',
    },
    {
        icon: FileCheck2,
        title: 'Revisión legal y de documentos',
        description: 'Verificamos factura de origen, historial de tenencias, multas, REPUVE y que el auto esté libre de gravámenes.',
    },
    {
        icon: Handshake,
        title: 'Acompañamiento en el trámite',
        description: 'Un asesor experto te acompaña desde la revisión física hasta la firma del contrato y la entrega de llaves.',
    },
    {
        icon: ShieldCheck,
        title: 'Protección ante vicios ocultos',
        description: 'Cobertura especializada ante fallas mecánicas mayores para que estrenes con total seguridad.',
    },
];

const process = [
    {
        icon: Search,
        title: '1. Elige tu auto',
        description: 'Busca en nuestro catálogo certificado o tráenos el auto que encontraste en cualquier plataforma.',
    },
    {
        icon: Car,
        title: '2. Lo inspeccionamos',
        description: 'Enviamos a nuestros inspectores a realizar la revisión mecánica y legal de 160 puntos.',
    },
    {
        icon: Handshake,
        title: '3. Firma segura',
        description: 'Elaboramos contratos protegidos y gestionamos el pago seguro sin riesgo de fraude.',
    },
    {
        icon: ClipboardCheck,
        title: '4. Entrega garantizada',
        description: 'Recibe tu auto con su certificado oficial de compra segura y garantía activa.',
    },
];

const activeFaqCategory = ref('all');
const visibleFaqs = computed(() =>
    activeFaqCategory.value === 'all'
        ? faqs
        : faqs.filter((faq) => faq.category === activeFaqCategory.value),
);

// Cotizador de garantía
const priceMin = computed(() => props.quote?.price_min ?? 50000);
const priceMax = computed(() => props.quote?.price_max ?? 1500000);
const priceMid = computed(() => Math.round((priceMin.value + priceMax.value) / 2));
const serviceFeeRate = computed(() => props.quote?.service_fee_rate ?? 0.025);
const validDays = computed(() => props.quote?.valid_days ?? 15);
const contactEmail = computed(
    () => props.quote?.contact_email ?? props.settings?.contact_email ?? 'garantia@wecar.mx',
);

const form = reactive({
    full_name: props.profile?.name ?? '',
    email: props.profile?.email ?? '',
    phone: props.profile?.phone ?? '',
    car_brand: '',
    car_model: '',
    car_version: '',
    car_year: new Date().getFullYear() - 2,
    car_km: '' as number | string,
    car_price: Math.min(Math.max(250000, priceMin.value), priceMax.value),
});

const cargoServicio = computed(() => Math.round(Number(form.car_price) * serviceFeeRate.value));
const totalConServicio = computed(() => Number(form.car_price) + cargoServicio.value);

const submitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref<Record<string, string>>({});
const lastQuote = ref<{ folio: string; pdf_url: string; total: number; service_fee: number; valid_until: string | null } | null>(null);

const formatPrice = (val: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(val);

const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const submitQuote = async () => {
    if (!isAuthenticated.value) {
        router.visit('/login');
        return;
    }

    if (submitting.value) return;

    errorMessage.value = '';
    fieldErrors.value = {};

    const win = window.open('', '_blank');
    submitting.value = true;

    try {
        const response = await fetch('/garantia/cotizaciones', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                ...form,
                car_km: form.car_km === '' ? null : Number(form.car_km),
                car_price: Number(form.car_price),
                car_year: Number(form.car_year),
            }),
        });

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            win?.close();

            if (response.status === 401) {
                router.visit('/login');
                return;
            }

            if (response.status === 422) {
                fieldErrors.value = Object.fromEntries(
                    Object.entries(data?.errors ?? {}).map(([key, value]) => [
                        key,
                        Array.isArray(value) ? String(value[0]) : String(value),
                    ]),
                );
                errorMessage.value = 'Revisa los datos de la cotización.';
                return;
            }

            if (response.status === 429) {
                errorMessage.value = 'Demasiadas solicitudes. Intenta de nuevo en un minuto.';
                return;
            }

            errorMessage.value = data?.message || 'No pudimos generar la cotización.';
            return;
        }

        lastQuote.value = data;

        if (win) {
            win.location.href = data.pdf_url;
        }
    } catch {
        win?.close();
        errorMessage.value = 'Error de conexión. Intenta de nuevo.';
    } finally {
        submitting.value = false;
    }
};

const faqJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map((faq) => ({
        '@type': 'Question',
        name: faq.question,
        acceptedAnswer: { '@type': 'Answer', text: faq.answer },
    })),
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <component :is="'script'" type="application/ld+json" v-text="JSON.stringify(faqJsonLd)" />

    <!-- 1. HERO CON SENTRA-2026.JPG -->
    <section class="relative isolate overflow-hidden bg-gradient-hero">
        <div class="absolute inset-0 -z-10">
            <img
                src="/images/sentra-2026.jpg"
                alt="Nissan Sentra 2026 protegido por garantía WeCar"
                class="h-full w-full scale-95 translate-x-[6%] object-cover object-right opacity-60 sm:translate-x-[10%] sm:opacity-75"
                loading="eager"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-background via-background/85 to-background/20" />
        </div>

        <div class="relative mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-1.5 text-xs font-bold tracking-wider text-accent-foreground">
                    <span class="h-2 w-2 rounded-full bg-primary animate-pulse" />
                    Garantía y Compra Segura
                </span>

                <h1 class="page-title mt-5 text-secondary text-3xl sm:text-4xl lg:text-5xl">
                    Compra tu auto <br />
                    <span class="text-primary">de forma 100% segura</span>
                </h1>

                <p class="page-subtitle mt-5 max-w-lg font-medium text-secondary/80 text-sm sm:text-base leading-relaxed">
                    Te acompañamos en todo el proceso. Validamos el vehículo mecánica y legalmente para que tomes la mejor decisión sin sorpresas ni fraudes.
                </p>

                <div class="mt-6 inline-flex flex-col items-start gap-1 rounded-2xl border border-primary/30 bg-primary/10 p-4 font-semibold text-secondary shadow-soft">
                    <span class="text-xl sm:text-2xl font-black text-primary">
                        Cargo por servicio desde 2.5%
                    </span>
                    <span class="text-xs text-muted-foreground">
                        Cotizado sobre el valor del auto. Incluye validación de 160 puntos y acompañamiento legal.
                    </span>
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <Button as="a" href="#cotizador" variant="hero" size="lg" class="shadow-elegant">
                        Simula el costo del servicio
                    </Button>
                    <Button as="a" href="/comprar" variant="outline" size="lg">
                        Ver autos verificados
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. QUÉ INCLUYE LA GARANTÍA -->
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-xs font-bold uppercase tracking-wider text-primary">Protección integral</span>
            <h2 class="section-title mt-2 text-secondary">Qué incluye nuestra garantía</h2>
            <p class="mt-2 text-sm text-muted-foreground">Un estándar de calidad creado para que compres con la misma confianza que en agencia.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="item in includes"
                :key="item.title"
                class="rounded-3xl border border-white/10 bg-secondary p-6 text-secondary-foreground shadow-soft transition-all hover:-translate-y-1 hover:border-primary/50 hover:shadow-elegant"
            >
                <div class="flex items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/15 text-primary">
                        <component :is="item.icon" class="h-6 w-6" stroke-width="2.2" />
                    </span>
                    <h3 class="min-w-0 text-base font-black text-white">{{ item.title }}</h3>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-secondary-foreground/75 sm:text-sm">{{ item.description }}</p>
            </div>
        </div>
    </section>

    <!-- 3. CÓMO FUNCIONA EL PROCESO -->
    <section class="border-y border-border bg-secondary px-4 py-16 sm:px-6 text-white">
        <div class="mx-auto max-w-7xl">
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-wider text-primary">Paso a paso</span>
                <h2 class="section-title mt-2 text-white">¿Cómo funciona la compra segura?</h2>
                <p class="mt-2 text-sm text-white/70">Un proceso transparente diseñado para ahorrarte tiempo, dinero y riesgos.</p>
            </div>

            <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <li
                    v-for="item in process"
                    :key="item.title"
                    class="relative rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur"
                >
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground font-black">
                            <component :is="item.icon" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-white/75 sm:text-sm">{{ item.description }}</p>
                        </div>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <!-- 4. COTIZADOR INTERACTIVO DE SERVICIO -->
    <section id="cotizador" class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <div class="rounded-3xl border border-border bg-card p-6 sm:p-10 shadow-soft">
            <div class="text-center max-w-lg mx-auto">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary uppercase">
                    <Sparkles class="h-3.5 w-3.5" /> Cotizador transparente
                </span>
                <h2 class="text-2xl font-black text-secondary mt-3">Cotiza el costo de tu Compra Segura</h2>
                <p class="text-xs text-muted-foreground mt-1">
                    Completa los datos del vehículo y genera tu cotización en PDF con folio y vigencia de {{ validDays }} días.
                </p>
            </div>

            <div class="mt-8 space-y-6">
                <!-- Datos del vehículo -->
                <div>
                    <h3 class="text-sm font-black text-secondary">Datos del vehículo</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="car-brand">Marca *</Label>
                            <Input id="car-brand" v-model="form.car_brand" class="mt-1.5" placeholder="Ej. Toyota" />
                            <p v-if="fieldErrors.car_brand" class="mt-1 text-xs text-destructive">{{ fieldErrors.car_brand }}</p>
                        </div>
                        <div>
                            <Label for="car-model">Modelo *</Label>
                            <Input id="car-model" v-model="form.car_model" class="mt-1.5" placeholder="Ej. Corolla" />
                            <p v-if="fieldErrors.car_model" class="mt-1 text-xs text-destructive">{{ fieldErrors.car_model }}</p>
                        </div>
                        <div>
                            <Label for="car-version">Versión</Label>
                            <Input id="car-version" v-model="form.car_version" class="mt-1.5" placeholder="Ej. LE CVT" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="car-year">Año *</Label>
                                <Input id="car-year" v-model.number="form.car_year" type="number" min="1990" :max="new Date().getFullYear() + 1" class="mt-1.5" />
                                <p v-if="fieldErrors.car_year" class="mt-1 text-xs text-destructive">{{ fieldErrors.car_year }}</p>
                            </div>
                            <div>
                                <Label for="car-km">Kilometraje</Label>
                                <Input id="car-km" v-model="form.car_km" type="number" min="0" placeholder="Opcional" class="mt-1.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos de contacto -->
                <div>
                    <h3 class="text-sm font-black text-secondary">Tus datos</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="full-name">Nombre completo *</Label>
                            <Input id="full-name" v-model="form.full_name" class="mt-1.5" autocomplete="name" />
                            <p v-if="fieldErrors.full_name" class="mt-1 text-xs text-destructive">{{ fieldErrors.full_name }}</p>
                        </div>
                        <div>
                            <Label for="email">Correo *</Label>
                            <Input id="email" v-model="form.email" type="email" autocomplete="email" class="mt-1.5" />
                            <p v-if="fieldErrors.email" class="mt-1 text-xs text-destructive">{{ fieldErrors.email }}</p>
                        </div>
                        <div>
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" type="tel" autocomplete="tel" class="mt-1.5" placeholder="Opcional" />
                        </div>
                    </div>
                </div>

                <!-- Precio -->
                <div>
                    <div class="flex justify-between items-center text-sm font-bold">
                        <Label for="car-price">Precio del auto:</Label>
                        <span class="text-lg font-black text-secondary">{{ formatPrice(Number(form.car_price) || 0) }}</span>
                    </div>
                    <input
                        id="car-price"
                        v-model.number="form.car_price"
                        type="range"
                        :min="priceMin"
                        :max="priceMax"
                        step="10000"
                        class="h-2 w-full accent-primary mt-2"
                    />
                    <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                        <span>{{ formatPrice(priceMin) }}</span>
                        <span>{{ formatPrice(priceMid) }}</span>
                        <span>{{ formatPrice(priceMax) }}</span>
                    </div>
                    <p v-if="fieldErrors.car_price" class="mt-1 text-xs text-destructive">{{ fieldErrors.car_price }}</p>
                </div>

                <div class="rounded-2xl border border-primary/30 bg-accent/50 p-5 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Cargo por servicio ({{ (serviceFeeRate * 100).toFixed(2).replace(/\.?0+$/, '') }}%):</span>
                        <span class="font-bold text-secondary">{{ formatPrice(cargoServicio) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Inspección de 160 puntos:</span>
                        <span class="font-bold text-emerald-600">Incluida</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Validación legal y de documentos:</span>
                        <span class="font-bold text-emerald-600">Incluida</span>
                    </div>
                    <div class="border-t border-border/80 pt-3 flex justify-between items-center text-base font-black">
                        <span class="text-secondary">Inversión total del auto:</span>
                        <span class="text-xl text-primary">{{ formatPrice(totalConServicio) }}</span>
                    </div>
                </div>

                <div v-if="errorMessage" class="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm font-semibold text-destructive">
                    {{ errorMessage }}
                </div>

                <div v-if="lastQuote" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
                    <p class="font-bold text-emerald-700">Cotización {{ lastQuote.folio }} generada.</p>
                    <p class="mt-1 text-muted-foreground">
                        Total {{ formatPrice(lastQuote.total) }} · Vigencia hasta
                        {{ lastQuote.valid_until ? new Date(lastQuote.valid_until).toLocaleDateString('es-MX') : `${validDays} días` }}.
                    </p>
                    <a :href="lastQuote.pdf_url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1.5 font-bold text-primary hover:underline">
                        <Download class="h-4 w-4" /> Descargar PDF
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Button
                        variant="hero"
                        size="lg"
                        class="w-full shadow-elegant font-bold"
                        :disabled="submitting"
                        @click="submitQuote"
                    >
                        <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
                        <Download v-else class="h-4 w-4" />
                        {{ submitting ? 'Generando…' : 'Cotizar y generar PDF' }}
                    </Button>
                    <Button as="a" href="/contacto" variant="outline" size="lg" class="w-full font-bold">
                        Solicitar revisión para mi auto
                    </Button>
                </div>

                <p v-if="!isAuthenticated" class="text-center text-xs text-muted-foreground">
                    Para generar tu cotización necesitas
                    <a href="/login" class="font-bold text-primary hover:underline">iniciar sesión</a>
                    o
                    <a href="/registro" class="font-bold text-primary hover:underline">crear una cuenta</a>.
                </p>

                <p class="text-center text-[11px] text-muted-foreground">
                    Cotización informativa con vigencia de {{ validDays }} días. Dudas: {{ contactEmail }}.
                </p>
            </div>
        </div>
    </section>

    <!-- 5. PREGUNTAS FRECUENTES -->
    <section id="faq" class="mx-auto max-w-4xl px-4 pb-16 sm:px-6">
        <h2 class="section-title text-center text-secondary">Preguntas frecuentes</h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-sm text-muted-foreground">
            Encuentra respuestas sobre garantía, búsqueda, publicación, crédito, pagos y tu cuenta.
        </p>

        <div class="mx-auto mt-6 max-w-md">
            <Label for="faq-category">Filtrar por categoría</Label>
            <select
                id="faq-category"
                v-model="activeFaqCategory"
                class="field-select mt-1.5 h-10 w-full rounded-md border border-input bg-background pl-3 pr-10 text-sm"
            >
                <option value="all">Todas las categorías</option>
                <option v-for="category in faqCategories" :key="category.id" :value="category.id">
                    {{ category.label }}
                </option>
            </select>
            <p class="mt-2 text-center text-xs text-muted-foreground">
                {{ visibleFaqs.length }} {{ visibleFaqs.length === 1 ? 'pregunta' : 'preguntas' }}
            </p>
        </div>

        <div class="mt-8 space-y-4">
            <details
                v-for="faq in visibleFaqs"
                :key="faq.id"
                class="group rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:border-primary/40"
            >
                <summary class="flex cursor-pointer items-center justify-between text-sm sm:text-base font-bold text-secondary list-none">
                    <span>{{ faq.question }}</span>
                    <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition group-open:rotate-180" />
                </summary>
                <p class="mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ faq.answer }}</p>
            </details>
        </div>
    </section>
</template>
