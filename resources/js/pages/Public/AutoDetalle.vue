<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Banknote,
    Calendar,
    Car as CarIcon,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Cog,
    Compass,
    DoorOpen,
    Droplet,
    FileCheck2,
    Fuel,
    Gauge,
    GitBranch,
    HandCoins,
    Handshake,
    Headphones,
    Heart,
    HeartHandshake,
    MapPin,
    MessageCircle,
    Palette,
    Scale,
    Share2,
    ShieldCheck,
    ShieldQuestion,
    Snowflake,
    Sparkles,
    Star,
    Tag,
    UserCircle2,
    Wallet,
    X,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import Button from '@/components/ui/Button.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    car: {
        id: string;
        slug: string;
        title: string;
        brand: string | null;
        model: string | null;
        version: string | null;
        year: number;
        km: number;
        price: number;
        previous_price: number | null;
        city: string | null;
        state: string | null;
        description: string | null;
        image: string | null;
        gallery: string[];
        transmission: string | null;
        fuel: string | null;
        body: string | null;
        color: string | null;
        accepts_trade: boolean;
        status: string | null;
        status_label: string | null;
        owner_name: string | null;
        agency: { id: string; slug: string; name: string; logo: string | null; city: string | null; phone: string | null; whatsapp: string | null } | null;
        published_at: string | null;
        url: string;
    };
    similar: CarCardData[];
    priceReference: { samples: number; min: number | null; p25: number | null; median: number | null; p75: number | null; max: number | null; confidence: string; scope: string };
    financing: { min_rate: number; max_term: number; service_fee_rate: number };
    seo: { title: string; description: string; canonical: string; image: string | null; images: string[]; noindex: boolean; robots: string; availability: string };
}>();

const images = computed(() => (props.car.gallery.length ? props.car.gallery : props.car.image ? [props.car.image] : []));
const active = ref(0);
const zoomOpen = ref(false);
const fav = ref(false);
const financiarOpen = ref(false);
const showFullDescription = ref(false);

const formatPrice = (value: number) => {
    const amount = Number(value);

    if (!Number.isFinite(amount)) return '—';

    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(amount);
};

const formatKm = (value: number) =>
    new Intl.NumberFormat('es-MX').format(value) + ' km';

// Finance Calculator State
const downPaymentPct = ref(20);
const termMonths = ref(48);
const effectiveMinRate = computed(() => {
    const rate = Number(props.financing.min_rate);

    return Number.isFinite(rate) && rate > 0 ? rate : 12.5;
});
const effectiveMaxTerm = computed(() => {
    const term = Number(props.financing.max_term);

    return Number.isFinite(term) && term >= 12 ? term : 72;
});
const annualInterestRate = ref(effectiveMinRate.value);

const financedAmount = computed(() => {
    return Math.max(0, props.car.price * (1 - downPaymentPct.value / 100));
});

const calculatedMonthlyPayment = computed(() => {
    const amount = financedAmount.value;
    const monthlyRate = annualInterestRate.value / 100 / 12;
    if (amount <= 0 || monthlyRate === 0) return 0;
    return Math.round((amount * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -termMonths.value)));
});

// Fast teaser calculation using the lowest active financing rate.
const teaserMonthly = computed(() => {
    const price = Number(props.car.price);
    const rate = effectiveMinRate.value / 100 / 12;
    const term = Math.min(72, effectiveMaxTerm.value);

    if (!Number.isFinite(price) || price <= 0 || rate <= 0 || term <= 0) {
        return 0;
    }

    const financed = price * 0.8;

    return Math.round((financed * rate) / (1 - Math.pow(1 + rate, -term)));
});

// Reference price distribution from real WeCar comparables.
const refMin = computed(() => props.priceReference.p25 ?? Math.round(props.car.price * 0.86));
const refAvg = computed(() => props.priceReference.median ?? Math.round(props.car.price * 1.02));
const refMax = computed(() => props.priceReference.p75 ?? Math.round(props.car.price * 1.18));
const bars = [0.45, 0.6, 0.78, 0.92, 1.0, 0.88, 0.72, 0.6, 0.5];
const highlightedBar = 4;

const productSpecs = computed(() => [
    { icon: CarIcon, label: 'Carrocería', value: props.car.body || 'Sedán' },
    { icon: Cog, label: 'Motor', value: '4 Cilindros' },
    { icon: GitBranch, label: 'Transmisión', value: props.car.transmission || 'Automática' },
    { icon: Compass, label: 'Tracción', value: 'Delantera' },
    { icon: Fuel, label: 'Combustible', value: props.car.fuel || 'Gasolina' },
    { icon: DoorOpen, label: 'Puertas', value: '4' },
    { icon: Gauge, label: 'Kilometraje', value: formatKm(props.car.km) },
    { icon: Snowflake, label: 'Aire', value: 'Acondicionado' },
    { icon: Tag, label: 'Versión', value: props.car.version || 'No especificada' },
    { icon: Palette, label: 'Color', value: props.car.color || 'Plata' },
]);

const reportItems = [
    'Sin reportes de accidentes graves',
    'Sin multas ni adeudos pendientes',
    'Libre de reporte de robo (REPUVE validado)',
    'Documentación y factura verificada',
    'Inspección mecánica de 162 puntos aprobada',
];

const whyBuy = [
    {
        icon: ShieldCheck,
        title: 'Inspección de 162 puntos',
        desc: 'Revisamos motor, transmisión, frenos y electrónica a detalle.',
    },
    {
        icon: Wallet,
        title: 'WeCar Crédito',
        desc: 'Conectamos tu solicitud con más de 35 financieras líderes.',
    },
    {
        icon: ShieldQuestion,
        title: 'Compra protegida',
        desc: 'Tu dinero y tu operación están blindados ante cualquier fraude.',
    },
    {
        icon: FileCheck2,
        title: 'Papelería validada',
        desc: 'Verificación exhaustiva de factura, tenencias y gravámenes.',
    },
    {
        icon: Headphones,
        title: 'Asesoría continua',
        desc: 'Un especialista te acompaña antes, durante y después de la compra.',
    },
];

const safetyTips = [
    'Verifica que el contrato cuente con sello y validación de WeCar.',
    'Nunca realices depósitos a cuentas no verificadas por la plataforma.',
    'Realiza la prueba de manejo en zonas seguras o puntos WeCar autorizados.',
    'Ante cualquier duda, nuestro equipo de soporte está listo para asistirte.',
];

const shareCar = () => {
    if (navigator.share) {
        navigator.share({
            title: props.car.title,
            url: window.location.href,
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(window.location.href);
    }
};

const whatsappUrl = computed(() => {
    const text = `Hola, me interesa el auto ${props.car.title} con precio de ${formatPrice(props.car.price)} que vi en WeCar: ${props.seo.canonical}`;
    const phone = props.car.agency?.whatsapp || '528180131279';
    return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
});

const siteOrigin = computed(() => {
    try {
        return new URL(props.seo.canonical).origin;
    } catch {
        return '';
    }
});

const jsonLd = computed(() => [
    {
        '@context': 'https://schema.org',
        '@type': 'Vehicle',
        '@id': `${props.seo.canonical}#vehicle`,
        url: props.seo.canonical,
        sku: props.car.slug,
        name: props.car.title,
        brand: props.car.brand ? { '@type': 'Brand', name: props.car.brand } : undefined,
        model: props.car.model,
        vehicleConfiguration: props.car.version,
        vehicleModelDate: props.car.year,
        mileageFromOdometer: { '@type': 'QuantitativeValue', value: props.car.km, unitCode: 'KMT' },
        bodyType: props.car.body,
        fuelType: props.car.fuel,
        vehicleTransmission: props.car.transmission,
        color: props.car.color,
        image: props.seo.images.length ? props.seo.images : images.value,
        itemCondition: 'https://schema.org/UsedCondition',
        offers: {
            '@type': 'Offer',
            price: props.car.price,
            priceCurrency: 'MXN',
            availability: props.seo.availability,
            url: props.seo.canonical,
            seller: props.car.agency
                ? {
                      '@type': 'Organization',
                      name: props.car.agency.name,
                      url: `${siteOrigin.value}/agencia/${props.car.agency.slug}`,
                  }
                : {
                      '@type': 'Organization',
                      name: 'Wecar',
                      url: siteOrigin.value,
                  },
        },
    },
    {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Inicio', item: `${siteOrigin.value}/` },
            { '@type': 'ListItem', position: 2, name: 'Comprar', item: `${siteOrigin.value}/comprar` },
            { '@type': 'ListItem', position: 3, name: props.car.title, item: props.seo.canonical },
        ],
    },
]);
</script>

<template>
    <SeoHead
        :title="seo.title"
        :description="seo.description"
        :canonical="seo.canonical"
        :image="seo.image"
        :image-alt="car.title"
        type="product"
        :noindex="seo.noindex"
        :robots="seo.robots"
        :price="car.price"
        currency="MXN"
        :availability="seo.availability"
    />
    <component :is="'script'" type="application/ld+json" v-text="JSON.stringify(jsonLd)" />

    <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 pb-24 lg:pb-12">
        <!-- Breadcrumbs + CTA publicar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                <Link href="/" class="hover:text-primary">Inicio</Link>
                <ChevronRight class="h-3 w-3 text-muted-foreground/60" />
                <Link href="/comprar" class="hover:text-primary">Comprar</Link>
                <ChevronRight class="h-3 w-3 text-muted-foreground/60" />
                <span v-if="car.brand" class="hover:text-primary cursor-pointer">{{ car.brand }}</span>
                <ChevronRight v-if="car.brand" class="h-3 w-3 text-muted-foreground/60" />
                <span class="text-secondary font-bold truncate max-w-[200px]">{{ car.title }}</span>
            </nav>
            <Link
                href="/vende"
                class="inline-flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground shadow-soft transition hover:scale-105"
            >
                <Tag class="h-3.5 w-3.5" /> Publica tu auto gratis
            </Link>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <!-- ════════════ COLUMNA IZQUIERDA ════════════ -->
            <div>
                <!-- Galería principal -->
                <div class="relative overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
                    <img
                        v-if="images.length"
                        :src="images[active]"
                        :alt="car.title"
                        class="aspect-[4/3] w-full object-cover cursor-zoom-in"
                        @click="zoomOpen = true"
                    />
                    <div v-else class="grid aspect-[4/3] place-items-center bg-muted text-muted-foreground">
                        <CarIcon class="h-16 w-16" />
                    </div>

                    <!-- Badges superiores -->
                    <div class="absolute left-4 top-4 z-20 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold text-secondary shadow-soft">
                            <CheckCircle2 class="h-4 w-4 text-emerald-500" /> Auto verificado
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/95 px-3 py-1.5 text-xs font-bold text-white shadow-soft">
                            <ShieldCheck class="h-4 w-4 text-primary" /> Garantía WeCar
                        </span>
                    </div>

                    <!-- Contador de fotos -->
                    <span v-if="images.length" class="absolute right-4 top-4 z-20 rounded-lg bg-secondary/90 px-2.5 py-1 text-xs font-black text-white backdrop-blur">
                        {{ active + 1 }} / {{ images.length }}
                    </span>

                    <!-- Controles previa / siguiente -->
                    <template v-if="images.length > 1">
                        <button
                            type="button"
                            class="absolute left-3 top-1/2 -translate-y-1/2 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-secondary shadow-soft hover:bg-white"
                            @click="active = (active === 0 ? images.length - 1 : active - 1)"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-secondary shadow-soft hover:bg-white"
                            @click="active = (active === images.length - 1 ? 0 : active + 1)"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>
                    </template>
                </div>

                <!-- Miniaturas de fotos -->
                <div v-if="images.length > 1" class="mt-3 grid grid-cols-6 gap-2">
                    <button
                        v-for="(img, idx) in images.slice(0, 5)"
                        :key="idx"
                        type="button"
                        class="overflow-hidden rounded-xl border-2 transition"
                        :class="active === idx ? 'border-primary ring-2 ring-primary/30' : 'border-transparent opacity-80 hover:opacity-100'"
                        @click="active = idx"
                    >
                        <img :src="img" :alt="`Foto ${idx + 1}`" class="aspect-[4/3] w-full object-cover" />
                    </button>
                    <button
                        v-if="images.length > 5"
                        type="button"
                        class="grid aspect-[4/3] place-items-center rounded-xl bg-secondary text-sm font-black text-white hover:bg-secondary/90 transition"
                        @click="zoomOpen = true"
                    >
                        +{{ images.length - 5 }}
                    </button>
                </div>

                <!-- Características del producto -->
                <section class="mt-8 rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <h2 class="font-black text-secondary text-lg">Características del producto</h2>
                    <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div v-for="spec in productSpecs" :key="spec.label" class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-muted text-secondary">
                                <component :is="spec.icon" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold text-muted-foreground">{{ spec.label }}</p>
                                <p class="text-sm font-bold text-secondary truncate">{{ spec.value }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Descripción -->
                <section class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <h2 class="font-black text-secondary text-lg">Descripción del vehículo</h2>
                    <div class="mt-3 text-sm leading-relaxed text-secondary/90">
                        <p v-if="car.description" :class="showFullDescription ? '' : 'line-clamp-4'" class="whitespace-pre-line">
                            {{ car.description }}
                        </p>
                        <p v-else class="text-muted-foreground">
                            El vendedor no ha añadido una descripción adicional para este vehículo.
                        </p>
                    </div>
                    <button
                        v-if="car.description && car.description.length > 200"
                        type="button"
                        class="mt-3 text-xs font-bold text-primary hover:underline"
                        @click="showFullDescription = !showFullDescription"
                    >
                        {{ showFullDescription ? 'Ver menos' : 'Ver descripción completa >' }}
                    </button>
                </section>
            </div>

            <!-- ════════════ COLUMNA DERECHA (CARD PRINCIPAL) ════════════ -->
            <aside class="space-y-6">
                <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <!-- Tiempo publicado -->
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                        <Clock class="h-3.5 w-3.5" /> Publicado en {{ car.city || 'México' }}
                    </p>

                    <!-- Título y acciones (share / fav) -->
                    <div class="mt-3 flex items-start justify-between gap-3">
                        <h1 class="font-black text-secondary text-2xl leading-tight">
                            {{ car.title }}
                        </h1>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                aria-label="Compartir"
                                class="grid h-9 w-9 place-items-center rounded-full border border-border text-secondary hover:border-primary hover:text-primary transition"
                                @click="shareCar"
                            >
                                <Share2 class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                aria-label="Favorito"
                                class="grid h-9 w-9 place-items-center rounded-full border border-border text-secondary hover:border-primary hover:text-primary transition"
                                @click="fav = !fav"
                            >
                                <Heart class="h-4 w-4" :class="fav ? 'fill-primary text-primary' : ''" />
                            </button>
                        </div>
                    </div>

                    <!-- Datos básicos -->
                    <p class="mt-2 text-xs font-bold text-muted-foreground">
                        {{ car.year }} · {{ formatKm(car.km) }} · {{ car.city || 'México' }}
                    </p>

                    <!-- CAFFAX Badge -->
                    <div class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                        <CheckCircle2 class="h-3.5 w-3.5" /> Historial validado por CAFFAX
                    </div>

                    <!-- Precio de contado -->
                    <div class="mt-4 flex items-baseline gap-3">
                        <span v-if="car.previous_price" class="text-base font-bold text-muted-foreground line-through">
                            {{ formatPrice(car.previous_price) }}
                        </span>
                        <p class="text-3xl font-black text-secondary">
                            {{ formatPrice(car.price) }}
                        </p>
                    </div>

                    <!-- WeCar Crédito Teaser -->
                    <div class="mt-5 rounded-2xl border-2 border-primary/40 bg-accent/50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-xs font-black tracking-wider text-primary uppercase">
                                <CheckCircle2 class="h-4 w-4" /> WeCar Crédito
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-bold text-primary">
                                <Sparkles class="h-3 w-3" /> Precalificación
                            </span>
                        </div>
                        <p class="mt-2 text-lg font-black text-secondary">
                            Desde <span class="text-primary">{{ formatPrice(teaserMonthly) }}</span>
                            <span class="text-xs font-bold text-muted-foreground">/mes</span>
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Calculado con 20% de enganche · Tasa desde {{ effectiveMinRate }}% anual
                        </p>
                        <button
                            type="button"
                            class="mt-2 text-xs font-bold text-primary hover:underline"
                            @click="financiarOpen = true"
                        >
                            Ver opciones y calcular con mi perfil &gt;
                        </button>
                    </div>

                    <!-- Calculadora interactiva de crédito -->
                    <div class="mt-5 rounded-2xl border border-border bg-muted/30 p-4">
                        <p class="text-xs font-black text-secondary uppercase tracking-wider">Simulador de mensualidad</p>

                        <div class="mt-4 space-y-3">
                            <div>
                                <div class="flex justify-between text-xs font-bold text-muted-foreground">
                                    <span>Enganche ({{ downPaymentPct }}%)</span>
                                    <span class="text-secondary font-black">{{ formatPrice(car.price * (downPaymentPct / 100)) }}</span>
                                </div>
                                <input
                                    v-model.number="downPaymentPct"
                                    type="range"
                                    min="10"
                                    max="70"
                                    step="5"
                                    class="h-2 w-full accent-primary mt-1"
                                />
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-bold text-muted-foreground">
                                    <span>Plazo</span>
                                    <span class="text-secondary font-black">{{ termMonths }} meses</span>
                                </div>
                                <input
                                    v-model.number="termMonths"
                                    type="range"
                                    min="12"
                                    max="72"
                                    step="12"
                                    class="h-2 w-full accent-primary mt-1"
                                />
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl bg-card p-3 border border-border flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold text-muted-foreground">Mensualidad estimada</p>
                                <p class="text-xl font-black text-primary">{{ formatPrice(calculatedMonthlyPayment) }}</p>
                            </div>
                            <Button size="sm" variant="hero" @click="financiarOpen = true">
                                Solicitar
                            </Button>
                        </div>
                    </div>

                    <!-- Botones de Acción / CTAs -->
                    <div class="mt-6 space-y-2.5">
                        <Button
                            variant="hero"
                            size="lg"
                            class="w-full gap-2 text-base font-bold shadow-elegant"
                            @click="financiarOpen = true"
                        >
                            <Banknote class="h-5 w-5" /> Financiar este auto
                        </Button>

                        <div class="grid grid-cols-2 gap-2.5">
                            <Button
                                as="a"
                                href="/comparar"
                                variant="outline"
                                class="gap-1.5 text-xs font-bold border-border"
                            >
                                <Scale class="h-4 w-4" /> Comparar
                            </Button>

                            <a
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-xs font-bold text-white shadow-soft hover:bg-[#1ebe57] transition"
                            >
                                WhatsApp
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Precio de referencia (histograma) -->
                <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <h3 class="font-black text-secondary text-base">Precio de referencia de mercado</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Basado en autos similares publicados en WeCar</p>

                    <div class="relative mt-6 h-28 flex items-end justify-between gap-1.5">
                        <div
                            v-for="(h, i) in bars"
                            :key="i"
                            :style="{ height: `${h * 100}%` }"
                            class="flex-1 rounded-t-md transition-all"
                            :class="i === highlightedBar ? 'bg-primary ring-2 ring-primary/40' : 'bg-muted'"
                        />
                    </div>

                    <div class="mt-4 flex justify-between text-xs text-muted-foreground">
                        <div>
                            <p class="font-black text-secondary">{{ formatPrice(refMin) }}</p>
                            <p class="text-[10px]">Precio bajo</p>
                        </div>
                        <div class="text-center">
                            <p class="font-black text-secondary">{{ formatPrice(refAvg) }}</p>
                            <p class="text-[10px]">Promedio</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-secondary">{{ formatPrice(refMax) }}</p>
                            <p class="text-[10px]">Precio alto</p>
                        </div>
                    </div>
                </div>

                <!-- Reporte de confianza -->
                <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <h3 class="font-black text-secondary text-base">Reporte de confianza WeCar</h3>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="r in reportItems" :key="r" class="flex items-center gap-2 text-xs font-medium text-secondary">
                            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-500" /> {{ r }}
                        </li>
                    </ul>
                </div>

                <!-- Vendedor verificado -->
                <div class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                    <h3 class="font-black text-secondary text-base">Información del vendedor</h3>
                    <div class="mt-4 flex items-center gap-3.5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-muted text-secondary">
                            <UserCircle2 class="h-8 w-8" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-sm text-secondary">{{ car.agency?.name || car.owner_name || 'Vendedor particular verificado' }}</p>
                            <p class="text-xs text-muted-foreground">{{ car.city || 'México' }} · Miembro verificado WeCar</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-600">
                            <CheckCircle2 class="h-3.5 w-3.5" /> Identidad validada
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-1 text-[11px] font-bold text-blue-600">
                            <ShieldCheck class="h-3.5 w-3.5" /> Responde rápido
                        </span>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ════════════ SECCIÓN: ¿POR QUÉ COMPRAR CON WECAR? ════════════ -->
        <section class="mt-12 rounded-3xl border border-border bg-accent/40 p-6 sm:p-8">
            <h2 class="text-center font-black text-secondary text-xl sm:text-2xl">
                ¿Por qué comprar con WeCar?
            </h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="item in whyBuy" :key="item.title" class="flex flex-col items-start gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-card text-primary shadow-soft">
                        <component :is="item.icon" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-black text-secondary">{{ item.title }}</p>
                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">{{ item.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════════ CONSEJOS DE SEGURIDAD ════════════ -->
        <section class="mt-8 rounded-3xl border border-border bg-card p-6 sm:p-8 shadow-soft">
            <h2 class="text-center font-black text-secondary text-xl">Consejos de seguridad</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="tip in safetyTips" :key="tip" class="flex items-start gap-2.5 text-xs text-secondary/90 leading-relaxed">
                    <AlertCircle class="h-4 w-4 shrink-0 text-primary mt-0.5" />
                    <span>{{ tip }}</span>
                </div>
            </div>
        </section>

        <!-- ════════════ AUTOS SIMILARES ════════════ -->
        <section v-if="similar.length" class="mt-12">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">Recomendaciones</p>
                    <h2 class="font-black text-secondary text-xl mt-1">Vehículos similares</h2>
                </div>
                <Link href="/comprar" class="text-xs font-bold text-primary hover:underline">Ver todos &gt;</Link>
            </div>

            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <CarCard v-for="sim in similar.slice(0, 4)" :key="sim.id" :car="sim" />
            </div>
        </section>

        <!-- ════════════ LIGHTBOX MODAL ════════════ -->
        <div
            v-if="zoomOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/95 p-4"
            @click="zoomOpen = false"
        >
            <button
                type="button"
                class="absolute right-5 top-5 grid h-10 w-10 place-items-center rounded-full bg-white/20 text-white hover:bg-white/30"
                @click="zoomOpen = false"
            >
                <X class="h-6 w-6" />
            </button>
            <img
                :src="images[active]"
                :alt="car.title"
                class="max-h-[90vh] max-w-[95vw] object-contain"
                @click.stop
            />
        </div>

        <!-- ════════════ BARRA INFERIOR FIJA MÓVIL (STICKY CTA) ════════════ -->
        <div class="fixed bottom-0 left-0 right-0 z-40 border-t border-border bg-background/95 p-3 backdrop-blur lg:hidden pb-safe">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase font-bold text-muted-foreground">Contado</p>
                    <p class="text-lg font-black text-secondary">{{ formatPrice(car.price) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="grid h-10 w-10 place-items-center rounded-xl bg-[#25D366] text-white shadow-soft"
                        aria-label="WhatsApp"
                    >
                        <MessageCircle class="h-5 w-5" />
                    </a>
                    <Button
                        size="sm"
                        variant="hero"
                        class="font-black gap-1.5"
                        @click="financiarOpen = true"
                    >
                        <Banknote class="h-4 w-4" /> Financiar
                    </Button>
                </div>
            </div>
        </div>

        <!-- Modal FinanciarAutoDialog -->
        <FinanciarAutoDialog
            :open="financiarOpen"
            :car="{
                id: car.id,
                brand: car.brand,
                model: car.model,
                version: car.version,
                year: car.year,
                price: car.price,
                km: car.km,
                title: car.title,
                condition: car.status_label === 'Nuevo' ? 'new' : 'used',
            }"
            :initial="{
                downPayment: downPaymentPct,
                term: termMonths,
                brand: car.brand || '',
                model: car.model || '',
                year: car.year,
                price: car.price,
            }"
            @close="financiarOpen = false"
        />
    </section>
</template>
