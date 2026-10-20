<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Eye, Heart, ShieldCheck, Sparkles, Target, Users } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Testimonios from '@/components/site/Testimonios.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
    settings?: { title?: string; subtitle?: string; mission?: string; vision?: string; values?: string };
    seo: { title: string; description: string; canonical: string };
}>();

const values = [
    {
        icon: Eye,
        title: 'Transparencia',
        description: 'Información clara, precios justos y procesos honestos en cada paso.',
    },
    {
        icon: Heart,
        title: 'Cercanía',
        description: 'Te tratamos como persona, no como un folio. Acompañamiento real y humano.',
    },
    {
        icon: Sparkles,
        title: 'Innovación',
        description: 'Tecnología de vanguardia que simplifica lo que antes era largo y complicado.',
    },
    {
        icon: ShieldCheck,
        title: 'Confianza',
        description: 'Validamos cada auto y cada operación para que compres con total seguridad.',
    },
];

const stats = [
    { number: '+1,500', label: 'Autos verificados' },
    { number: '98%', label: 'Clientes satisfechos' },
    { number: '162', label: 'Puntos de inspección' },
    { number: '35+', label: 'Financieras aliadas' },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <!-- 1. HERO CON IMAGEN DE FONDO -->
    <section class="relative isolate flex min-h-[70vh] items-center overflow-hidden text-white">
        <img
            src="/images/nosotros-team.webp"
            alt="Equipo WeCar acompañando a clientes en México"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
            loading="eager"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/85 via-secondary/75 to-secondary/95" />

        <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-[11px] font-bold tracking-[0.2em] backdrop-blur-md uppercase text-primary">
                <Sparkles class="h-3 w-3" /> Nosotros · WeCar México
            </span>

            <h1 class="page-title mt-6 max-w-3xl text-white">
                Transformamos la forma de comprar y vender autos en México
            </h1>

            <p class="mt-6 max-w-2xl text-base text-white/80 sm:text-lg leading-relaxed">
                {{ settings?.subtitle || 'Nacimos con una convicción clara: comprar o vender un auto seminuevo no debería ser un proceso estresante, inseguro ni lleno de incertidumbre. Construimos la plataforma donde la confianza es el estándar.' }}
            </p>

            <div class="mt-10 flex flex-wrap gap-4">
                <Button as="a" href="/comprar" variant="hero" size="lg" class="shadow-elegant">
                    Ver catálogo de autos <ArrowRight class="h-4 w-4 ml-1" />
                </Button>
                <Button as="a" href="/contacto" variant="outline" size="lg" class="border-white/30 text-white hover:bg-white/10">
                    Contáctanos
                </Button>
            </div>
        </div>
    </section>

    <!-- 2. MÉTRICAS CLAVE -->
    <section class="border-b border-border bg-card px-4 py-10 sm:px-6">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 sm:grid-cols-4 text-center">
            <div v-for="s in stats" :key="s.label" class="p-4">
                <p class="text-3xl font-black text-primary sm:text-4xl">{{ s.number }}</p>
                <p class="mt-1 text-xs font-semibold text-muted-foreground sm:text-sm">{{ s.label }}</p>
            </div>
        </div>
    </section>

    <!-- 3. LO QUE NOS DEFINE (VALORES) -->
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center max-w-2xl mx-auto">
            <p class="text-xs font-bold uppercase tracking-wider text-primary">Nuestros principios</p>
            <h2 class="section-title mt-2 text-secondary">Lo que nos define</h2>
            <p class="mt-2 text-sm text-muted-foreground">Pilares que guían cada decisión y cada acompañamiento con nuestros clientes.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="value in values"
                :key="value.title"
                class="rounded-3xl border border-border bg-card p-7 shadow-soft transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant"
            >
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <component :is="value.icon" class="h-6 w-6" stroke-width="2.2" />
                </span>
                <h3 class="mt-5 text-lg font-black text-secondary">{{ value.title }}</h3>
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">{{ value.description }}</p>
            </div>
        </div>
    </section>

    <!-- 4. MISIÓN Y VISIÓN -->
    <section class="border-y border-border bg-muted/30 px-4 py-16 sm:px-6">
        <div class="mx-auto grid max-w-7xl gap-8 md:grid-cols-2">
            <div class="rounded-3xl border border-border bg-card p-8 shadow-soft">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <Target class="h-5 w-5 text-primary" />
                    </span>
                    <h2 class="text-xl font-black text-secondary">Nuestra Misión</h2>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-secondary/80 sm:text-base">
                    {{ settings?.mission || 'Brindar certidumbre absoluta y transparencia total en la compra y venta de autos seminuevos en México, combinando inspección mecánica rigurosa, validación legal y soluciones financieras accesibles.' }}
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-8 shadow-soft">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-accent-foreground">
                        <Eye class="h-5 w-5 text-primary" />
                    </span>
                    <h2 class="text-xl font-black text-secondary">Nuestra Visión</h2>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-secondary/80 sm:text-base">
                    {{ settings?.vision || 'Convertirnos en el ecosistema digital automotriz de referencia en México, donde cualquier persona pueda comprar, vender o financiar un auto con la misma confianza y simplicidad que cualquier servicio digital moderno.' }}
                </p>
            </div>
        </div>
    </section>

    <!-- 5. TESTIMONIOS (trasladados de Home a Nosotros según el plan) -->
    <Testimonios />

    <!-- 6. CIERRE INSPIRACIONAL -->
    <section class="bg-secondary px-4 py-20 text-center text-white sm:px-6">
        <div class="mx-auto max-w-3xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-1.5 text-[11px] font-bold tracking-[0.25em] backdrop-blur-md uppercase text-primary">
                <Heart class="h-3 w-3" /> Nuestro Porqué
            </span>
            <h2 class="section-title mt-6 text-white text-3xl sm:text-4xl">
                Detrás de cada auto hay <span class="text-primary">una historia</span>
            </h2>
            <p class="mt-6 text-sm text-white/75 sm:text-base leading-relaxed">
                Sabemos que comprar un auto es mucho más que una transacción: es un sueño, una herramienta de trabajo y libertad para tu familia. Por eso existimos: para que esa decisión la tomes con claridad, seguridad y el respaldo de un equipo que te acompaña de verdad.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <Button as="a" href="/comprar" variant="hero" size="lg">
                    Explorar catálogo de autos <ArrowRight class="h-4 w-4 ml-1" />
                </Button>
                <Button as="a" href="/credito" variant="outline" size="lg" class="border-white/20 text-white hover:bg-white/10">
                    Simular financiamiento
                </Button>
            </div>
        </div>
    </section>
</template>
