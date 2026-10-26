<script setup lang="ts">
import { ArrowRight, BadgeDollarSign, CheckCircle2, Landmark, ShieldCheck, Sparkles } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';

interface PublicationPlan {
    id: string;
    key_name: string;
    name: string;
    price: number | string;
    duration: string | null;
    max_cars: number;
    highlight: boolean;
}

defineProps<{ plans: PublicationPlan[] }>();

const benefits = [
    {
        icon: ShieldCheck,
        eyebrow: 'Para comprar',
        title: 'Compra segura',
        description: 'Autos verificados con inspección mecánica y legal, garantía y acompañamiento en cada paso.',
    },
    {
        icon: Landmark,
        eyebrow: 'Para financiar',
        title: 'Financiamiento a tu medida',
        description: '+35 bancos y financieras, desde 10% de enganche y trámite 100% digital.',
    },
    {
        icon: BadgeDollarSign,
        eyebrow: 'Para vender',
        title: 'Vende en 2 minutos',
        description: 'Publica tu auto, recibe ofertas de compradores reales y cobra de forma segura.',
    },
];

const formatPrice = (value: number | string): string =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(Number(value));

const priceLabel = (plan: PublicationPlan): string =>
    Number(plan.price) === 0 ? 'Gratis' : formatPrice(plan.price);
</script>

<template>
    <section class="border-t border-border bg-background px-4 py-16 sm:px-6" aria-labelledby="compra-vende-title">
        <div class="mx-auto max-w-7xl">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-primary">
                    <Sparkles class="h-3.5 w-3.5" /> Compra y vende con Wecar
                </span>
                <h2 id="compra-vende-title" class="section-title mt-4 text-secondary">
                    Todo para comprar o vender tu auto, en un solo lugar
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-muted-foreground sm:text-base">
                    Compra con garantía, financia a tu medida y vende con el paquete que mejor se adapte a ti.
                </p>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-3">
                <article
                    v-for="benefit in benefits"
                    :key="benefit.title"
                    class="group flex flex-col rounded-3xl border border-border bg-card p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant"
                >
                    <div class="grid h-14 w-14 place-items-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20 transition-transform duration-300 group-hover:scale-110">
                        <component :is="benefit.icon" class="h-7 w-7" stroke-width="2.2" />
                    </div>
                    <p class="mt-5 text-[11px] font-black uppercase tracking-wider text-primary">{{ benefit.eyebrow }}</p>
                    <h3 class="mt-1 text-lg font-black text-secondary">{{ benefit.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ benefit.description }}</p>
                </article>
            </div>

            <div class="relative mt-12 overflow-hidden rounded-[2rem] bg-gradient-dark p-6 text-secondary-foreground shadow-elegant sm:p-8 lg:p-10">
                <div class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />

                <div class="relative">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-primary">Paquetes para publicar</p>
                            <h3 class="mt-2 text-2xl font-black text-white sm:text-3xl">Elige cómo quieres vender tu auto</h3>
                        </div>
                        <p class="max-w-sm text-xs leading-relaxed text-white/60 sm:text-right">
                            Todos incluyen 30 días de publicación, fotos y ficha técnica completa.
                        </p>
                    </div>

                    <div v-if="plans.length" class="mt-8 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="plan in plans"
                            :key="plan.id"
                            class="relative flex flex-col rounded-2xl border p-5"
                            :class="plan.highlight ? 'border-primary/70 bg-primary/15 ring-2 ring-primary/25' : 'border-white/10 bg-white/5'"
                        >
                            <span
                                v-if="plan.highlight"
                                class="absolute -top-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 rounded-full bg-primary px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-primary-foreground shadow-soft"
                            >
                                <Sparkles class="h-3 w-3" /> Más popular
                            </span>

                            <p class="text-xs font-black uppercase tracking-wider text-primary">{{ plan.name }}</p>
                            <p class="mt-2 text-3xl font-black text-white">{{ priceLabel(plan) }}</p>
                            <p class="mt-1 text-xs text-white/60">{{ plan.duration || '30 días' }} de publicación</p>

                            <ul class="mt-5 space-y-2 border-t border-white/10 pt-4 text-xs text-white/75">
                                <li class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    Hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto' : 'autos' }} publicado{{ plan.max_cars === 1 ? '' : 's' }}
                                </li>
                                <li class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    Ficha completa con fotos
                                </li>
                                <li v-if="plan.highlight" class="flex items-start gap-2">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    Posición destacada en el catálogo
                                </li>
                            </ul>
                        </article>
                    </div>

                    <p v-else class="mt-8 rounded-2xl border border-dashed border-white/15 px-4 py-5 text-center text-sm text-white/60">
                        Los paquetes disponibles se muestran al iniciar tu publicación.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 sm:flex-row">
                        <p class="text-center text-xs text-white/60 sm:text-left">
                            Publica gratis con el plan Básico y cambia cuando quieras.
                        </p>
                        <Button as="a" href="/vende" size="lg" variant="hero" class="w-full gap-2 sm:w-auto">
                            Publicar mi auto <ArrowRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
