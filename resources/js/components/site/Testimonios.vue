<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ArrowLeft, ArrowRight, Quote, Star } from '@lucide/vue';

interface Testimonial {
    name: string;
    city: string;
    text: string;
    rating: number;
    avatar: string;
}

const items: Testimonial[] = [
    {
        name: 'Andrea M.',
        city: 'CDMX',
        text: 'El proceso fue súper rápido y transparente. Me ayudaron con todo el papeleo.',
        rating: 5,
        avatar: '/images/testimonios/andrea.jpg',
    },
    {
        name: 'Luis R.',
        city: 'Monterrey',
        text: 'Vendí mi auto en una semana al precio que esperaba. 100% recomendado.',
        rating: 5,
        avatar: '/images/testimonios/luis.jpg',
    },
    {
        name: 'Paola G.',
        city: 'Guadalajara',
        text: 'La validación mecánica me dio mucha confianza para comprar. Increíble servicio.',
        rating: 5,
        avatar: '/images/testimonios/paola.jpg',
    },
    {
        name: 'Carlos T.',
        city: 'Puebla',
        text: 'Atención impecable. Encontré el auto que buscaba a un precio justo.',
        rating: 5,
        avatar: '/images/testimonios/carlos.jpg',
    },
    {
        name: 'María F.',
        city: 'Querétaro',
        text: 'Todo el proceso digital y sin complicaciones. Volvería a usar Wecar sin dudar.',
        rating: 5,
        avatar: '/images/testimonios/maria.jpg',
    },
    {
        name: 'Jorge S.',
        city: 'León',
        text: 'Me asesoraron en cada paso. La garantía me dio total tranquilidad.',
        rating: 5,
        avatar: '/images/testimonios/jorge.jpg',
    },
];

const track = ref<HTMLElement | null>(null);
const currentIndex = ref(0);
const visible = ref(1);
const isPaused = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const maxIndex = computed(() => Math.max(0, items.length - visible.value));
const dots = computed(() => maxIndex.value + 1);

const step = (): number => {
    const first = track.value?.children[0] as HTMLElement | undefined;

    if (!track.value || !first) {
        return 0;
    }

    const gap = Number.parseFloat(getComputedStyle(track.value).columnGap || '0');
    const width = first.getBoundingClientRect().width;

    return width + (Number.isFinite(gap) ? gap : 0);
};

const updateVisible = () => {
    const width = track.value?.clientWidth ?? 0;
    const unit = step();

    if (!width || !unit) {
        visible.value = 1;
        return;
    }

    visible.value = Math.max(1, Math.round((width + 24) / unit));
    currentIndex.value = Math.min(currentIndex.value, maxIndex.value);
};

const scrollToIndex = (index: number) => {
    const unit = step();

    if (!track.value || !unit) {
        return;
    }

    const target = Math.min(Math.max(index, 0), maxIndex.value);
    currentIndex.value = target;
    track.value.scrollTo({ left: target * unit, behavior: 'smooth' });
};

const next = () => {
    scrollToIndex(currentIndex.value >= maxIndex.value ? 0 : currentIndex.value + 1);
};

const prev = () => {
    scrollToIndex(currentIndex.value <= 0 ? maxIndex.value : currentIndex.value - 1);
};

const onScroll = () => {
    const unit = step();

    if (!track.value || !unit) {
        return;
    }

    currentIndex.value = Math.min(Math.max(Math.round(track.value.scrollLeft / unit), 0), maxIndex.value);
};

onMounted(() => {
    updateVisible();
    window.addEventListener('resize', updateVisible);

    timer = setInterval(() => {
        if (!isPaused.value) {
            next();
        }
    }, 4500);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateVisible);

    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <section
        class="bg-gradient-dark py-12 px-4 sm:py-16 sm:px-6 text-white overflow-hidden"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
        @focusin="isPaused = true"
        @focusout="isPaused = false"
    >
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <p class="text-xs font-bold tracking-wider text-primary uppercase">Experiencias reales</p>
                <h2 class="text-2xl font-black sm:text-3xl md:text-4xl mt-2">
                    Comentarios de nuestros clientes
                </h2>
            </div>

            <div class="relative mt-10">
                <div
                    ref="track"
                    class="hide-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth pb-2 pt-2"
                    aria-label="Testimonios de clientes"
                    tabindex="0"
                    @scroll.passive="onScroll"
                >
                    <article
                        v-for="t in items"
                        :key="t.name"
                        class="relative shrink-0 snap-start basis-full rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur transition hover:border-primary/40 hover:bg-white/[0.08] sm:basis-[calc((100%-1.5rem)/2)] lg:basis-[calc((100%-3rem)/3)]"
                    >
                        <Quote class="absolute right-5 top-5 h-10 w-10 text-primary/30" />
                        <div class="flex gap-1 text-primary">
                            <Star v-for="i in t.rating" :key="i" class="h-4 w-4 fill-current" />
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-white/90">"{{ t.text }}"</p>
                        <div class="mt-6 flex items-center gap-3">
                            <img
                                :src="t.avatar"
                                :alt="`Foto de ${t.name}`"
                                width="44"
                                height="44"
                                loading="lazy"
                                class="h-11 w-11 rounded-full object-cover ring-2 ring-primary/40"
                            />
                            <div>
                                <p class="text-sm font-bold">{{ t.name }}</p>
                                <p class="text-xs text-white/60">{{ t.city }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-8 flex items-center justify-center gap-4">
                    <button
                        type="button"
                        aria-label="Testimonio anterior"
                        class="grid h-9 w-9 place-items-center rounded-full border border-white/20 text-white transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                        @click="prev"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </button>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="(_, index) in dots"
                            :key="index"
                            type="button"
                            :aria-label="`Ir al grupo ${index + 1}`"
                            class="h-2 rounded-full transition-all"
                            :class="index === currentIndex ? 'w-6 bg-primary' : 'w-2 bg-white/30 hover:bg-white/50'"
                            @click="scrollToIndex(index)"
                        />
                    </div>

                    <button
                        type="button"
                        aria-label="Siguiente testimonio"
                        class="grid h-9 w-9 place-items-center rounded-full border border-white/20 text-white transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                        @click="next"
                    >
                        <ArrowRight class="h-4 w-4" />
                    </button>
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

@media (prefers-reduced-motion: reduce) {
    .hide-scrollbar {
        scroll-behavior: auto;
    }
}
</style>
