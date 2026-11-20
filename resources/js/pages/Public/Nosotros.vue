<script setup lang="ts">
import { ArrowRight, CheckCircle2, Eye, Heart, ShieldCheck, Sparkles, Target, Users } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Testimonios from '@/components/site/Testimonios.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
    settings?: { title?: string; subtitle?: string; mission?: string; vision?: string; values?: string };
    seo: { title: string; description: string; canonical: string };
}>();

const storyPoints = [
    'Una plataforma pensada para personas, no para folios.',
    'Procesos claros, sin sorpresas ni letras chiquitas.',
    'Tecnología al servicio de tu tranquilidad.',
    'Relaciones de largo plazo, no transacciones únicas.',
];

const values = [
    {
        icon: Eye,
        title: 'Transparencia',
        description: 'Información clara, precios justos y procesos honestos en cada paso.',
    },
    {
        icon: Heart,
        title: 'Cercanía',
        description: 'Te acompañamos antes, durante y después de tu compra para que siempre tengas un respaldo real.',
    },
    {
        icon: Sparkles,
        title: 'Innovación',
        description: 'Tecnología que simplifica lo que antes era largo y complicado.',
    },
    {
        icon: ShieldCheck,
        title: 'Confianza',
        description: 'Validamos a cada usuario para que cada operación sea más segura.',
    },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <!-- 1. Hero -->
    <section class="relative isolate flex min-h-[68vh] items-center overflow-hidden text-white">
        <img
            src="/images/credito-hero.jpg"
            alt="Equipo WeCar acompañando a clientes en México"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
            loading="eager"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/85 via-secondary/75 to-secondary/95" />

        <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:py-28">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-primary backdrop-blur-md">
                <Sparkles class="h-3 w-3" /> Nosotros · WeCar.mx
            </span>

            <h1 class="page-title mt-6 max-w-4xl text-white">
                Conoce la <span class="text-primary">historia</span> detrás de WeCar
            </h1>

            <p class="mt-6 max-w-2xl text-base leading-relaxed text-white/80 sm:text-lg">
                Una startup mexicana que nació para transformar la forma en que las personas compran su próximo auto seminuevo: con transparencia, tecnología y acompañamiento real.
            </p>

            <p class="mt-8 inline-flex items-center gap-2 text-xs font-semibold text-white/65">
                <span class="h-px w-8 bg-primary" /> Construyendo confianza desde 2026
            </p>
        </div>
    </section>

    <!-- 2. Nuestra historia -->
    <section class="bg-background px-4 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Nuestra historia</p>
                <h2 class="section-title mt-3 text-secondary">Construyendo confianza en la industria automotriz</h2>
                <p class="mt-5 text-sm leading-relaxed text-muted-foreground sm:text-base">
                    WeCar nació en enero de 2026 con una idea sencilla pero poderosa: transformar la experiencia de comprar un auto seminuevo en México. Vimos un mercado lleno de fricciones, letras chiquitas y desconfianza, y decidimos hacer algo distinto.
                </p>
                <p class="mt-4 text-sm leading-relaxed text-muted-foreground sm:text-base">
                    Hoy somos un equipo mexicano apasionado por los autos y por las personas, que combina tecnología, financiamiento accesible y atención humana para acompañarte en una de las decisiones financieras más importantes de tu vida.
                </p>

                <ul class="mt-6 space-y-3">
                    <li v-for="point in storyPoints" :key="point" class="flex items-start gap-2.5 text-sm text-secondary">
                        <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        <span>{{ point }}</span>
                    </li>
                </ul>
            </div>

            <div class="relative">
                <div class="overflow-hidden rounded-[2rem] border border-border bg-card shadow-elegant">
                    <img
                        src="/images/nosotros-team-polos.jpg"
                        alt="Equipo WeCar"
                        class="aspect-[4/3] w-full object-cover"
                        loading="lazy"
                    />
                </div>
                <div class="absolute -bottom-5 left-5 flex items-center gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-elegant sm:left-8">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary">
                        <Users class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Equipo</p>
                        <p class="text-sm font-black text-secondary">Mexicanos apasionados</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Visión y misión -->
    <section class="border-y border-border bg-muted/30 px-4 py-16 sm:px-6 lg:py-20">
        <div class="mx-auto max-w-7xl">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Lo que nos mueve</p>
                <h2 class="section-title mt-3 text-secondary">Visión y misión</h2>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <article class="rounded-3xl border border-border bg-card p-7 shadow-soft sm:p-9">
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                            <Eye class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Visión</p>
                            <h3 class="text-lg font-black text-secondary">Un auto WeCar en cada hogar de México</h3>
                        </div>
                    </div>
                    <p class="mt-5 text-sm leading-relaxed text-muted-foreground sm:text-base">
                        Queremos ser la marca más confiable y querida para comprar un auto seminuevo en el país, llevando movilidad digna y financiamiento justo a millones de familias mexicanas.
                    </p>
                </article>

                <article class="rounded-3xl border border-border bg-card p-7 shadow-soft sm:p-9">
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                            <Target class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Misión</p>
                            <h3 class="text-lg font-black text-secondary">Ayudarte a encontrar tu auto ideal con seguridad y claridad</h3>
                        </div>
                    </div>
                    <p class="mt-5 text-sm leading-relaxed text-muted-foreground sm:text-base">
                        Acompañamos a cada persona en la compra de su próximo auto con procesos transparentes, tecnología útil y atención humana real, para que tomes la mejor decisión con total tranquilidad.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- 4. Valores -->
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-primary">Nuestros valores</p>
            <h2 class="section-title mt-3 text-secondary">Lo que nos define</h2>
            <p class="mt-3 text-sm text-muted-foreground sm:text-base">
                Cuatro principios que guían cada decisión y cada conversación en WeCar.
            </p>
        </div>

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <article
                v-for="value in values"
                :key="value.title"
                class="rounded-3xl border border-border bg-card p-6 shadow-soft transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant"
            >
                <div class="flex items-start gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <component :is="value.icon" class="h-6 w-6" stroke-width="2.1" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-lg font-black leading-snug text-secondary">{{ value.title }}</h3>
                        <p class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">{{ value.description }}</p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- 5. Testimonios -->
    <Testimonios />

    <!-- 6. Cierre -->
    <section class="bg-secondary px-4 py-20 text-center text-white sm:px-6">
        <div class="mx-auto max-w-3xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.25em] text-primary backdrop-blur-md">
                <Heart class="h-3 w-3" /> Nuestro porqué
            </span>
            <h2 class="section-title mt-6 text-3xl text-white sm:text-4xl">
                Detrás de cada auto hay <span class="text-primary">una historia</span>
            </h2>
            <p class="mt-6 text-sm leading-relaxed text-white/75 sm:text-base">
                Sabemos que comprar un auto es mucho más que una transacción: es un sueño, una herramienta de trabajo, libertad para tu familia. Por eso existimos: para que esa decisión la tomes con claridad, seguridad y la certeza de que alguien te acompaña de verdad.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <Button as="a" href="/comprar" variant="hero" size="lg" class="gap-2">
                    Explorar autos <ArrowRight class="h-4 w-4" />
                </Button>
                <Button as="a" href="/credito" variant="outlineOnDark" size="lg">
                    Solicitar financiamiento
                </Button>
            </div>
        </div>
    </section>
</template>
