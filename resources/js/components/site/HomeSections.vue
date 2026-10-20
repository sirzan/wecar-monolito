<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
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
    Tag,
    Wallet,
} from '@lucide/vue';
import Button from '@/components/ui/Button.vue';

// Mode for Publica2Min
const mode = ref<'comprar' | 'vender'>('comprar');
const stepIndex = ref(0);
let stepTimer: ReturnType<typeof setInterval> | null = null;
const isStepPaused = ref(false);

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

const partnerBrands = [
    { name: 'Google', slug: 'google', color: '4285F4' },
    { name: 'Meta', slug: 'meta', color: '0467DF' },
    { name: 'Facebook', slug: 'facebook', color: '1877F2' },
    { name: 'Instagram', slug: 'instagram', color: 'E4405F' },
    { name: 'WhatsApp', slug: 'whatsapp', color: '25D366' },
    { name: 'TikTok', slug: 'tiktok', color: '000000' },
    { name: 'Mercado Pago', slug: 'mercadopago', color: '00B1EA' },
    { name: 'Stripe', slug: 'stripe', color: '635BFF' },
];

const failedBrands = ref<Record<number, boolean>>({});

onMounted(() => {
    stepTimer = setInterval(() => {
        if (!isStepPaused.value) {
            stepIndex.value = (stepIndex.value + 1) % 3;
        }
    }, 2500);
});

onUnmounted(() => {
    if (stepTimer) clearInterval(stepTimer);
});
</script>

<template>
    <!-- ───────────────────────── 1. Garantía teaser ───────────────────────── -->
    <section class="bg-background py-8 px-4 sm:py-12 sm:px-6 md:py-16">
        <div class="mx-auto grid max-w-7xl items-center gap-6 sm:gap-8 md:grid-cols-2 md:gap-10">
            <div class="relative overflow-hidden rounded-[2rem] bg-gradient-dark p-6 text-white shadow-elegant sm:p-8 md:p-10">
                <ShieldCheck class="absolute -right-6 -top-6 h-36 w-36 text-primary/15 sm:h-52 sm:w-52" />
                <span class="inline-flex items-center gap-2 rounded-full bg-primary/20 px-3 py-1 text-xs font-bold tracking-wider text-primary-foreground">
                    <ShieldCheck class="h-3.5 w-3.5" /> Garantía Wecar
                </span>
                <h3 class="mt-4 text-xl font-black leading-tight sm:text-2xl md:text-3xl">
                    Compra con la <span class="text-primary">tranquilidad</span> que mereces
                </h3>
                <p class="mt-3 text-sm text-white/80 sm:text-base">
                    Evita riesgos innecesarios, vicios ocultos y fraudes con nuestra inspección mecánica y legal certificada.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur">
                        <CheckCircle2 class="h-4 w-4 text-primary" /> 162 puntos de inspección
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur">
                        <CheckCircle2 class="h-4 w-4 text-primary" /> Sin reporte de robo
                    </div>
                </div>
            </div>

            <div>
                <p class="text-xs font-bold tracking-wider text-primary uppercase">
                    ¿Cómo te protegemos?
                </p>
                <h2 class="section-title mt-2 text-secondary">Información de garantía</h2>
                <ul class="mt-6 space-y-4">
                    <li class="flex items-start gap-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <ShieldCheck class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Validación mecánica completa</p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Diagnóstico de motor, transmisión, frenos y sistemas electrónicos por técnicos certificados.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <FileCheck2 class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Revisión legal y de documentos</p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Verificamos factura, tenencias, multas y que el auto esté libre de gravámenes o reportes.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground">
                            <Handshake class="h-5 w-5 text-primary" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-secondary text-sm sm:text-base">Acompañamiento en todo el proceso</p>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">Un asesor experto te guía desde la elección hasta la firma del contrato y la entrega de llaves.</p>
                        </div>
                    </li>
                </ul>
                <div class="mt-8 flex">
                    <Button as="a" href="/garantia" variant="hero" size="pill" class="w-full sm:w-auto shadow-elegant">
                        Conoce más sobre la garantía
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- ───────────────────────── 2. Publica tu auto en 2 minutos ───────────────────────── -->
    <section
        class="relative overflow-hidden bg-secondary px-4 py-12 sm:px-6 sm:py-16 text-secondary-foreground"
        @mouseenter="isStepPaused = true"
        @mouseleave="isStepPaused = false"
    >
        <div class="relative mx-auto max-w-5xl">
            <div class="mx-auto max-w-2xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 text-xs font-bold text-primary-foreground tracking-wider uppercase">
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
            <div class="mx-auto mt-8 flex max-w-xs justify-center rounded-full border border-secondary-foreground/20 bg-secondary/80 p-1">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 rounded-full py-2 text-xs font-bold transition"
                    :class="mode === 'comprar' ? 'bg-primary text-primary-foreground shadow-soft' : 'text-secondary-foreground/70 hover:text-white'"
                    @click="mode = 'comprar'; stepIndex = 0;"
                >
                    <ShoppingCart class="h-3.5 w-3.5" /> Comprar
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 rounded-full py-2 text-xs font-bold transition"
                    :class="mode === 'vender' ? 'bg-primary text-primary-foreground shadow-soft' : 'text-secondary-foreground/70 hover:text-white'"
                    @click="mode = 'vender'; stepIndex = 0;"
                >
                    <Tag class="h-3.5 w-3.5" /> Vender
                </button>
            </div>

            <!-- Steps Grid -->
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <div
                    v-for="(s, idx) in (mode === 'comprar' ? compraSteps : ventaSteps)"
                    :key="s.title"
                    class="relative rounded-2xl border bg-card/10 p-6 backdrop-blur transition-all duration-300"
                    :class="stepIndex === idx ? 'border-primary ring-2 ring-primary/40 bg-card/20 -translate-y-1' : 'border-secondary-foreground/15 hover:border-secondary-foreground/30'"
                    @click="stepIndex = idx"
                >
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground font-black text-base shadow-soft">
                            <component :is="s.icon" class="h-6 w-6" />
                        </div>
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-secondary text-xs font-black text-primary border border-primary/40">
                            {{ idx + 1 }}
                        </span>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-white">{{ s.title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-secondary-foreground/80">{{ s.desc }}</p>
                </div>
            </div>

            <div class="mt-10 flex justify-center">
                <Button
                    as="a"
                    :href="mode === 'comprar' ? '/comprar' : '/vende'"
                    variant="hero"
                    size="pill"
                    class="shadow-elegant"
                >
                    {{ mode === 'comprar' ? 'Ver catálogo de autos' : 'Publicar mi auto ahora' }}
                </Button>
            </div>
        </div>
    </section>

    <!-- ───────────────────────── 3. Vendes con los mejores beneficios ───────────────────────── -->
    <section class="bg-background py-12 px-4 sm:py-16 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="mx-auto max-w-2xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-bold tracking-wider text-primary">
                    <BadgeDollarSign class="h-3.5 w-3.5" /> Ventajas Wecar
                </span>
                <h2 class="section-title mt-4 text-secondary">
                    Vendes con los mejores <span class="text-primary">beneficios</span>
                </h2>
                <p class="mt-2 text-sm text-muted-foreground sm:text-base">
                    La plataforma diseñada para que vendas tu auto rápido, seguro y al mejor precio.
                </p>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="b in beneficios"
                    :key="b.title"
                    class="group relative flex flex-col rounded-3xl border border-border bg-card p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant"
                >
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20 transition-transform duration-300 group-hover:scale-110">
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

    <!-- ───────────────────────── 4. Marcas aliadas (marquee continuo) ───────────────────────── -->
    <section class="border-t border-border bg-muted/20 py-10 px-4 sm:py-14 sm:px-6 overflow-hidden">
        <div class="mx-auto max-w-7xl text-center">
            <p class="text-xs font-bold text-primary tracking-wider uppercase">Aliados estratégicos</p>
            <h2 class="mt-2 text-xl font-black text-secondary sm:text-2xl md:text-3xl">
                El ecosistema inteligente de autos No. 1 de <span class="text-primary">México</span>
            </h2>
        </div>

        <div class="group relative mt-10 overflow-hidden">
            <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-20 bg-gradient-to-r from-background to-transparent" />
            <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-20 bg-gradient-to-l from-background to-transparent" />

            <div class="flex w-max animate-marquee gap-6 sm:gap-10 group-hover:[animation-play-state:paused]">
                <div
                    v-for="(brand, bIdx) in [...partnerBrands, ...partnerBrands, ...partnerBrands]"
                    :key="`${brand.slug}-${bIdx}`"
                    class="flex h-16 w-32 shrink-0 items-center justify-center rounded-2xl border border-border bg-card px-4 shadow-soft transition hover:-translate-y-1 sm:h-20 sm:w-40"
                    :title="brand.name"
                >
                    <img
                        v-if="!failedBrands[bIdx]"
                        :src="`https://cdn.simpleicons.org/${brand.slug}/${brand.color}`"
                        :alt="brand.name"
                        loading="lazy"
                        class="max-h-8 max-w-[80%] object-contain"
                        @error="failedBrands[bIdx] = true"
                    />
                    <span v-else class="text-xs font-extrabold text-secondary">{{ brand.name }}</span>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes marquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-33.33%);
    }
}

.animate-marquee {
    animation: marquee 25s linear infinite;
}
</style>
