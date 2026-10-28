<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ArrowRight, CheckCircle2, Sparkles } from '@lucide/vue';
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

const props = defineProps<{ plans: PublicationPlan[] }>();

const plansTrack = ref<HTMLElement | null>(null);
const isPlansPaused = ref(false);
const carouselPlans = computed(() => [...props.plans, ...props.plans]);
let plansTimer: ReturnType<typeof setInterval> | null = null;

const advancePlans = (): void => {
    const track = plansTrack.value;

    if (!track || props.plans.length < 1 || track.children.length < 2) return;

    const first = track.children.item(0) as HTMLElement;
    const second = track.children.item(1) as HTMLElement;
    const loopCard = track.children.item(props.plans.length) as HTMLElement | null;

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

onMounted(() => {
    if (props.plans.length < 2) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    plansTimer = setInterval(() => {
        if (!isPlansPaused.value) advancePlans();
    }, 4000);
});

onUnmounted(() => {
    if (plansTimer) clearInterval(plansTimer);
});

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
    <section class="border-t border-border bg-background px-4 py-16 sm:px-6" aria-labelledby="precios-publicacion">
        <div class="mx-auto max-w-7xl">
            <div
                class="relative overflow-hidden rounded-[2rem] bg-gradient-dark px-6 pb-6 pt-44 text-secondary-foreground shadow-elegant sm:px-8 sm:pb-8 sm:pt-64 lg:px-10 lg:pb-10 lg:pt-10"
            >
                <img
                    src="/images/quote-car-transparent.png"
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    width="1161"
                    height="574"
                    class="pointer-events-none absolute right-0 top-0 w-[88%] max-w-none select-none object-contain opacity-95 drop-shadow-[0_24px_40px_rgba(0,0,0,0.35)] sm:w-[64%] lg:inset-y-auto lg:bottom-4 lg:right-4 lg:top-auto lg:w-[46%]"
                />

                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-secondary via-secondary/90 to-secondary/25" />
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-secondary/10 to-secondary lg:hidden" />

                <div class="relative z-10 flex h-full flex-col">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">Paquetes para publicar</p>
                        <h2 id="precios-publicacion" class="mt-2 text-2xl font-black text-white sm:text-3xl">
                            Elige cómo quieres vender tu auto
                        </h2>
                    </div>

                    <div v-if="plans.length" class="mt-8 lg:pr-[28%]">
                        <div
                            ref="plansTrack"
                            class="hide-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 pt-3"
                            aria-label="Paquetes para publicar"
                            tabindex="0"
                            @mouseenter="isPlansPaused = true"
                            @mouseleave="isPlansPaused = false"
                            @focusin="isPlansPaused = true"
                            @focusout="isPlansPaused = false"
                        >
                            <article
                                v-for="(plan, index) in carouselPlans"
                                :key="`${plan.id}-${index}`"
                                class="relative flex shrink-0 snap-start basis-full flex-col rounded-2xl border p-4 backdrop-blur-sm sm:basis-[calc((100%-1rem)/2)] lg:basis-[calc((100%-2rem)/3)]"
                                :class="plan.highlight ? 'border-primary/70 bg-secondary/95 ring-2 ring-primary/25' : 'border-white/10 bg-secondary/85'"
                                :aria-hidden="index >= plans.length ? 'true' : undefined"
                            >
                                <span
                                    v-if="plan.highlight"
                                    class="absolute -top-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 whitespace-nowrap rounded-full bg-primary px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-primary-foreground shadow-soft"
                                >
                                    <Sparkles class="h-3 w-3" /> Más popular
                                </span>

                                <p class="text-xs font-black uppercase tracking-wider text-primary">{{ plan.name }}</p>
                                <p class="mt-2 text-2xl font-black text-white">{{ priceLabel(plan) }}</p>
                                <p class="mt-1 text-[11px] text-white/60">{{ plan.duration || '30 días' }} de publicación</p>

                                <ul class="mt-4 space-y-1.5 border-t border-white/10 pt-3 text-[11px] text-white/75">
                                    <li class="flex items-start gap-2">
                                        <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                        Hasta {{ plan.max_cars }} {{ plan.max_cars === 1 ? 'auto' : 'autos' }} publicado{{ plan.max_cars === 1 ? '' : 's' }}
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

                    <p v-else class="mt-8 rounded-2xl border border-dashed border-white/15 px-4 py-5 text-center text-sm text-white/60">
                        Los paquetes disponibles se muestran al iniciar tu publicación.
                    </p>

                    <div class="mt-auto flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 sm:flex-row lg:pr-[28%]">
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

<style scoped>
.hide-scrollbar {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
