<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeDollarSign,
    Camera,
    Car,
    CheckCircle2,
    Clock,
    FileCheck2,
    Handshake,
    Search,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Tag,
    Wallet,
} from '@lucide/vue';
import Button from '@/components/ui/Button.vue';

export interface PublicationPlan {
    id: string;
    key_name: string;
    name: string;
    price: number | string;
    duration: string | null;
    max_cars: number;
    highlight: boolean;
}

const props = defineProps<{ plans: PublicationPlan[] }>();

// Mode for Publica2Min
const mode = ref<'comprar' | 'vender'>('comprar');
const stepIndex = ref(0);
const isStepPaused = ref(false);
const stepsTrack = ref<HTMLElement | null>(null);
const benefitsTrack = ref<HTMLElement | null>(null);
const plansTrack = ref<HTMLElement | null>(null);
const isBenefitsPaused = ref(false);
const isPlansPaused = ref(false);
let stepTimer: ReturnType<typeof setInterval> | null = null;
let benefitsTimer: ReturnType<typeof setInterval> | null = null;
let plansTimer: ReturnType<typeof setInterval> | null = null;

const scrollTrackToIndex = (track: HTMLElement | null, index: number): void => {
    if (!track) return;

    const card = track.children.item(index) as HTMLElement | null;

    if (!card) return;

    const left = card.getBoundingClientRect().left - track.getBoundingClientRect().left + track.scrollLeft;
    track.scrollTo({ left, behavior: 'smooth' });
};

const advanceStep = (): void => {
    stepIndex.value = (stepIndex.value + 1) % 3;
    scrollTrackToIndex(stepsTrack.value, stepIndex.value);
};

const advanceLoopingTrack = (track: HTMLElement | null, itemCount: number): void => {
    if (!track || itemCount < 1 || track.children.length < 2) return;

    const first = track.children.item(0) as HTMLElement;
    const second = track.children.item(1) as HTMLElement;
    const loopCard = track.children.item(itemCount) as HTMLElement | null;

    if (!loopCard) return;

    const step = second.offsetLeft - first.offsetLeft;
    const loopOffset = loopCard.offsetLeft - first.offsetLeft;

    if (track.scrollLeft + step >= loopOffset - 4) {
        track.scrollTo({ left: loopOffset, behavior: 'smooth' });

        window.setTimeout(() => {
            if (track.scrollLeft >= loopOffset - 4) {
                track.scrollTo({ left: 0, behavior: 'auto' });
            }
        }, 450);

        return;
    }

    track.scrollBy({ left: step, behavior: 'smooth' });
};

const advanceBenefits = (): void => {
    advanceLoopingTrack(benefitsTrack.value, beneficios.length);
};

const advancePlans = (): void => {
    advanceLoopingTrack(plansTrack.value, props.plans.length);
};

const formatPrice = (value: number | string): string =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(Number(value));

const priceLabel = (plan: PublicationPlan): string =>
    Number(plan.price) === 0 ? 'Gratis' : formatPrice(plan.price);

const setMode = async (nextMode: 'comprar' | 'vender'): Promise<void> => {
    mode.value = nextMode;
    stepIndex.value = 0;
    await nextTick();
    scrollTrackToIndex(stepsTrack.value, 0);
};

const compraSteps = [
    {
        icon: Search,
        title: 'Busca tu auto',
        desc: 'Explora nuestro catálogo verificado y encuentra el auto ideal.',
    },
    {
        icon: ShieldCheck,
        title: 'Validamos por ti',
        desc: 'Revisión mecánica exhaustiva y legal para tu completa tranquilidad.',
    },
    {
        icon: FileCheck2,
        title: 'Compra segura',
        desc: 'Firma contratos protegidos y paga con total seguridad respaldada.',
    },
];

const ventaSteps = [
    {
        icon: Camera,
        title: 'Sube tus fotos',
        desc: 'Llena los datos básicos y fotos de tu auto en solo 2 minutos.',
    },
    {
        icon: BadgeDollarSign,
        title: 'Recibe ofertas',
        desc: 'Compradores reales y verificados interesados en tu vehículo.',
    },
    {
        icon: Wallet,
        title: 'Cobra seguro',
        desc: 'Te pagamos con total rapidez, transferencia protegida y sin riesgos.',
    },
];

const beneficios = [
    {
        icon: BadgeDollarSign,
        title: 'Vende más rápido y en menos tiempo',
        desc: 'Tasamos tu auto al mejor precio del mercado con nuestra tecnología y conectamos con compradores listos.',
    },
    {
        icon: ShieldCheck,
        title: '100% seguro',
        desc: 'Pagos protegidos y trámites cubiertos de principio a fin.',
    },
    {
        icon: Handshake,
        title: 'Te acompañamos',
        desc: 'Asesoría personalizada en cada paso del proceso de compra o venta.',
    },
    {
        icon: Wallet,
        title: 'Crédito disponible',
        desc: 'Más de 35 bancos y financieras afiliados para financiar tu vehículo.',
    },
];

const carouselBenefits = computed(() => [...beneficios, ...beneficios]);
const carouselPlans = computed(() => [...props.plans, ...props.plans]);

const partnerBrands = [
    { name: 'Google', slug: 'google', color: '4285F4', url: 'https://www.google.com' },
    { name: 'Meta', slug: 'meta', color: '0467DF', url: 'https://www.meta.com' },
    { name: 'TikTok', slug: 'tiktok', color: '000000', url: 'https://www.tiktok.com' },
    { name: 'Mercado Pago', slug: 'mercadopago', color: '00B1EA', url: 'https://www.mercadopago.com.mx' },
    { name: 'Stripe', slug: 'stripe', color: '635BFF', url: 'https://stripe.com/mx' },
];

const failedBrands = ref<Record<string, boolean>>({});

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    stepTimer = setInterval(() => {
        if (!isStepPaused.value) advanceStep();
    }, 2500);

    benefitsTimer = setInterval(() => {
        if (!isBenefitsPaused.value) advanceBenefits();
    }, 4000);

    if (props.plans.length > 1) {
        plansTimer = setInterval(() => {
            if (!isPlansPaused.value) advancePlans();
        }, 4000);
    }
});

onUnmounted(() => {
    if (stepTimer) clearInterval(stepTimer);
    if (benefitsTimer) clearInterval(benefitsTimer);
    if (plansTimer) clearInterval(plansTimer);
});
</script>

<template>
    <!-- ───────────────────────── 1. Garantía teaser ───────────────────────── -->
    <section class="bg-background py-8 px-4 sm:py-12 sm:px-6 md:py-16">
        <div class="mx-auto grid max-w-7xl items-center gap-6 sm:gap-8 md:grid-cols-2 md:gap-10">
            <div
                class="relative overflow-hidden rounded-[2rem] bg-gradient-dark p-6 text-white shadow-elegant sm:p-8 md:p-10">
                <ShieldCheck class="absolute -right-6 -top-6 h-36 w-36 text-primary/15 sm:h-52 sm:w-52" />
                <span
                    class="inline-flex items-center gap-2 rounded-full bg-primary/20 px-3 py-1 text-xs font-bold tracking-wider text-primary-foreground">
                    <ShieldCheck class="h-3.5 w-3.5" /> Garantía Wecar
                </span>
                <h3 class="mt-4 text-xl font-black leading-tight sm:text-2xl md:text-3xl">
                    Compra con la <span class="text-primary">tranquilidad</span> que mereces
                </h3>
                <p class="mt-3 text-sm text-white/80 sm:text-base">
                    Evita riesgos innecesarios, vicios ocultos y fraudes con nuestra inspección mecánica y legal
                    certificada.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <div
                        class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur">
                        <CheckCircle2 class="h-4 w-4 text-primary" /> 162 puntos de inspección
                    </div>
                    <div
                        class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur">
                        <CheckCircle2 class="h-4 w-4 text-primary" /> Sin reporte de robo
                    </div>
                </div>
                <div class="mt-6 flex">
                    <Button as="a" href="/garantia" variant="hero" size="pill" class="w-full shadow-elegant sm:w-auto">
                        Conoce más sobre la garantía
                    </Button>
                </div>
            </div>

            <div>
                <p class="text-xs font-bold tracking-wider text-primary uppercase">
                    ¿Cómo te protegemos?
                </p>
                <h2 class="section-title mt-2 text-secondary">Información de garantía</h2>
                <ul class="mt-6 space-y-4">
                    <li class="flex items-start gap-3.5">
                        <span
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <ShieldCheck class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Validación mecánica completa</p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Diagnóstico de motor,
                                transmisión, frenos y sistemas electrónicos por técnicos certificados.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3.5">
                        <span
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <FileCheck2 class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Revisión legal y de documentos</p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Verificamos factura, tenencias,
                                multas y que el auto esté libre de gravámenes o reportes.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3.5">
                        <span
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <Handshake class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Acompañamiento en todo el proceso
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Un asesor experto te guía desde
                                la elección hasta la firma del contrato y la entrega de llaves.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- ───────────────────────── 2. Vendes con los mejores beneficios ───────────────────────── -->
    <section class="bg-background py-12 px-4 sm:py-16 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="mx-auto max-w-2xl text-center">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-bold tracking-wider text-primary">
                    <BadgeDollarSign class="h-3.5 w-3.5" /> Ventajas Wecar
                </span>
                <h2 class="section-title mt-4 text-secondary">
                    Vendes con los mejores <span class="text-primary">beneficios</span>
                </h2>
                <p class="mt-2 text-sm text-muted-foreground sm:text-base">
                    La plataforma diseñada para que vendas tu auto rápido, seguro y al mejor precio.
                </p>
            </div>

            <div ref="benefitsTrack"
                class="hide-scrollbar mt-10 flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth pb-2 pt-2"
                aria-label="Beneficios de vender con Wecar" tabindex="0" @mouseenter="isBenefitsPaused = true"
                @mouseleave="isBenefitsPaused = false" @focusin="isBenefitsPaused = true"
                @focusout="isBenefitsPaused = false">
                <div v-for="(b, index) in carouselBenefits" :key="`${b.title}-${index}`"
                    class="group relative flex shrink-0 snap-start basis-full flex-col rounded-3xl border border-border bg-card p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant sm:basis-[calc((100%-1.5rem)/2)] lg:basis-[calc((100%-3rem)/3)]"
                    :aria-hidden="index >= beneficios.length ? 'true' : undefined">
                    <div
                        class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20 transition-transform duration-300 group-hover:scale-110">
                        <component :is="b.icon" class="h-7 w-7" stroke-width="2.2" />
                    </div>
                    <h3 class="mt-5 text-base font-bold text-secondary">{{ b.title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">{{ b.desc }}</p>
                </div>
            </div>

            <div class="mt-10 flex justify-center">
                <Button as="a" href="/vende" variant="hero" size="lg" class="shadow-elegant">
                    Publica gratis tu auto
                </Button>
            </div>
        </div>
    </section>

    <!-- ───────────────────────── 3. Publica tu auto en 2 minutos + planes ───────────────────────── -->
    <section class="bg-background px-4 py-12 sm:px-6 sm:py-16">
        <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gradient-dark p-6 text-secondary-foreground shadow-elegant sm:p-8 lg:p-10"
            @mouseenter="isStepPaused = true" @mouseleave="isStepPaused = false" @focusin="isStepPaused = true"
            @focusout="isStepPaused = false">
            <div class="relative z-10">
                <div class="mx-auto max-w-2xl text-center">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary-foreground">
                        <Clock class="h-3.5 w-3.5 text-primary" /> Rápido y sencillo
                    </span>
                    <h2 class="section-title mt-3 text-secondary-foreground">
                        Publica tu auto en <span class="text-primary">2 minutos</span>
                    </h2>
                    <p class="mt-2 text-sm text-secondary-foreground/75 sm:text-base">
                        Compra o vende en 3 pasos sencillos y transparentes.
                    </p>
                </div>

                <!-- Tabs selector -->
                <div
                    class="mx-auto mt-8 flex max-w-xs justify-center rounded-full border border-secondary-foreground/20 bg-secondary/80 p-1">
                    <button type="button"
                        class="flex flex-1 items-center justify-center gap-2 rounded-full py-2 text-xs font-bold transition"
                        :class="mode === 'comprar' ? 'bg-primary text-primary-foreground shadow-soft' : 'text-secondary-foreground/70 hover:text-white'"
                        @click="setMode('comprar')">
                        <ShoppingCart class="h-3.5 w-3.5" /> Comprar
                    </button>
                    <button type="button"
                        class="flex flex-1 items-center justify-center gap-2 rounded-full py-2 text-xs font-bold transition"
                        :class="mode === 'vender' ? 'bg-primary text-primary-foreground shadow-soft' : 'text-secondary-foreground/70 hover:text-white'"
                        @click="setMode('vender')">
                        <Tag class="h-3.5 w-3.5" /> Vender
                    </button>
                </div>

                <!-- Carrusel de pasos -->
                <div ref="stepsTrack"
                    class="hide-scrollbar mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 pt-2"
                    aria-label="Pasos para comprar o vender" tabindex="0">
                    <div v-for="(s, idx) in (mode === 'comprar' ? compraSteps : ventaSteps)" :key="`${mode}-${s.title}`"
                        class="relative flex shrink-0 snap-start basis-full flex-col rounded-2xl border bg-card/10 p-5 backdrop-blur transition-all duration-300 sm:basis-[calc((100%-1rem)/2)] lg:basis-[calc((100%-2rem)/3)]"
                        :class="stepIndex === idx ? 'border-primary ring-2 ring-primary/40 bg-card/20 -translate-y-1' : 'border-secondary-foreground/15 hover:border-secondary-foreground/30'"
                        @click="stepIndex = idx; scrollTrackToIndex(stepsTrack, idx)">
                        <div class="flex items-start gap-4">
                            <div
                                class="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary text-base font-black text-primary-foreground shadow-soft">
                                <component :is="s.icon" class="h-6 w-6" />
                                <span
                                    class="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-secondary text-[10px] font-black text-primary ring-2 ring-primary/50">
                                    {{ idx + 1 }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold leading-snug text-white">{{ s.title }}</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-secondary-foreground/80">{{ s.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Planes dentro de la misma tarjeta -->
                <div class="relative mt-10 border-t border-white/10 pt-8">
                    <img src="/images/quote-car-transparent.png" alt="" aria-hidden="true" loading="lazy"
                        decoding="async" width="1161" height="574"
                        class="pointer-events-none absolute right-0 top-0 w-[88%] max-w-none select-none object-contain opacity-95 drop-shadow-[0_24px_40px_rgba(0,0,0,0.35)] sm:w-[64%] lg:inset-y-auto lg:bottom-0 lg:right-0 lg:top-auto lg:w-[46%]" />
                    <div
                        class="pointer-events-none absolute inset-0 bg-gradient-to-r from-secondary via-secondary/85 to-secondary/20" />

                    <div class="relative z-10 pt-36 sm:pt-56 lg:pt-0">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-wider text-primary">Paquetes para publicar
                            </p>
                            <h3 class="mt-2 text-2xl font-black text-white sm:text-3xl">
                                Elige cómo quieres vender tu auto
                            </h3>
                        </div>

                        <div v-if="plans.length" class="mt-6 lg:pr-[28%]">
                            <div ref="plansTrack"
                                class="hide-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 pt-3"
                                aria-label="Paquetes para publicar" tabindex="0" @mouseenter="isPlansPaused = true"
                                @mouseleave="isPlansPaused = false" @focusin="isPlansPaused = true"
                                @focusout="isPlansPaused = false">
                                <article v-for="(plan, index) in carouselPlans" :key="`${plan.id}-${index}`"
                                    class="relative flex shrink-0 snap-start basis-full flex-col rounded-2xl border p-4 backdrop-blur-sm sm:basis-[calc((100%-1rem)/2)] lg:basis-[calc((100%-2rem)/3)]"
                                    :class="plan.highlight ? 'border-primary/70 bg-secondary/95 ring-2 ring-primary/25' : 'border-white/10 bg-secondary/85'"
                                    :aria-hidden="index >= plans.length ? 'true' : undefined">
                                    <span v-if="plan.highlight"
                                        class="absolute -top-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 whitespace-nowrap rounded-full bg-primary px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-primary-foreground shadow-soft">
                                        <Sparkles class="h-3 w-3" /> Más popular
                                    </span>

                                    <p class="text-xs font-black uppercase tracking-wider text-primary">{{ plan.name }}
                                    </p>
                                    <p class="mt-2 text-2xl font-black text-white">{{ priceLabel(plan) }}</p>
                                    <p class="mt-1 text-[11px] text-white/60">{{ plan.duration || '30 días' }} de
                                        publicación</p>

                                    <ul
                                        class="mt-4 space-y-1.5 border-t border-white/10 pt-3 text-[11px] text-white/75">
                                        <li class="flex items-start gap-2">
                                            <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                            Hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto' : 'autos' }}
                                            publicado{{ plan.max_cars === 1 ? '' : 's' }}
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                            Ficha completa con fotos
                                        </li>
                                        <li v-if="plan.highlight" class="flex items-start gap-2">
                                            <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                            Posición destacada en el catálogo
                                        </li>
                                    </ul>
                                </article>
                            </div>
                        </div>

                        <p v-else
                            class="mt-6 rounded-2xl border border-dashed border-white/15 px-4 py-5 text-center text-sm text-white/60">
                            Los paquetes disponibles se muestran al iniciar tu publicación.
                        </p>

                        <div
                            class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 sm:flex-row lg:pr-[28%]">
                            <p class="text-center text-xs text-white/60 sm:text-left">
                                Publica gratis con el plan Básico y cambia cuando quieras.
                            </p>
                            <Button as="a" href="/vende" size="lg" variant="hero" class="w-full gap-2 sm:w-auto">
                                Publicar mi auto
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ───────────────────────── 4. Marcas aliadas (marquee continuo) ───────────────────────── -->
    <section class="border-t border-border bg-muted/20 py-10 px-4 sm:py-14 sm:px-6 overflow-hidden">
        <div class="mx-auto max-w-7xl text-center">
            <p class="text-xs font-bold text-primary tracking-wider uppercase">Aliados estratégicos</p>
            <h2 class="mt-2 text-lg font-black leading-tight text-secondary sm:text-2xl md:text-3xl">
                <span class="block">El buscador inteligente</span>
                <span class="block">No. 1 de <span class="text-primary">México</span></span>
            </h2>
        </div>

        <div class="brands-viewport relative mt-10 overflow-hidden">
            <div class="brands-track">
                <div v-for="groupIndex in 2" :key="groupIndex" class="brands-group"
                    :aria-hidden="groupIndex === 2 ? 'true' : undefined">
                    <div v-for="(brand, bIdx) in partnerBrands" :key="`${groupIndex}-${brand.slug}-${bIdx}`"
                        class="brand-slot">
                        <a :href="brand.url" target="_blank" rel="noopener noreferrer"
                            class="flex h-20 w-full items-center justify-center rounded-2xl border border-border bg-card px-4 shadow-soft transition hover:-translate-y-1 sm:h-24"
                            :title="`Visitar ${brand.name}`" :aria-label="`Visitar ${brand.name}`"
                            :tabindex="groupIndex === 2 ? -1 : undefined">
                            <img v-if="!failedBrands[`${groupIndex}-${bIdx}`]"
                                :src="`https://cdn.simpleicons.org/${brand.slug}/${brand.color}`" :alt="brand.name"
                                loading="lazy" class="max-h-10 max-w-[72%] object-contain sm:max-h-14"
                                @error="failedBrands[`${groupIndex}-${bIdx}`] = true" />
                            <span v-else class="text-sm font-extrabold text-secondary">{{ brand.name }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hide-scrollbar {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.hide-scrollbar::-webkit-scrollbar {
    display: none;
}

.brands-viewport {
    --visible: 2.5;
    --gap: 1rem;
    --card-width: calc(100cqw / var(--visible));
    container-type: inline-size;
}

.brands-track {
    display: flex;
    width: calc(var(--card-width) * 10);
    animation: brands-step 20s ease-in-out infinite;
    will-change: transform;
}

.brands-group {
    display: flex;
}

.brand-slot {
    flex: 0 0 var(--card-width);
    padding: 0 calc(var(--gap) / 2);
}

.brands-viewport:hover .brands-track {
    animation-play-state: paused;
}

@keyframes brands-step {

    0%,
    16% {
        transform: translateX(0);
    }

    20%,
    36% {
        transform: translateX(calc(var(--card-width) * -1));
    }

    40%,
    56% {
        transform: translateX(calc(var(--card-width) * -2));
    }

    60%,
    76% {
        transform: translateX(calc(var(--card-width) * -3));
    }

    80%,
    96% {
        transform: translateX(calc(var(--card-width) * -4));
    }

    100% {
        transform: translateX(calc(var(--card-width) * -5));
    }
}

@media (min-width: 640px) {
    .brands-viewport {
        --visible: 3;
        --gap: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .brands-viewport {
        --visible: 5;
    }
}

@media (prefers-reduced-motion: reduce) {
    .brands-track {
        animation: none;
    }
}
</style>