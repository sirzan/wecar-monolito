<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Quote, Star } from '@lucide/vue';

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

const currentIndex = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;
const isPaused = ref(false);

const next = () => {
    currentIndex.value = (currentIndex.value + 1) % items.length;
};

const prev = () => {
    currentIndex.value = (currentIndex.value - 1 + items.length) % items.length;
};

onMounted(() => {
    timer = setInterval(() => {
        if (!isPaused.value) {
            next();
        }
    }, 4500);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <section
        class="bg-gradient-dark py-12 px-4 sm:py-16 sm:px-6 text-white overflow-hidden"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
    >
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <p class="text-xs font-bold tracking-wider text-primary uppercase">Experiencias reales</p>
                <h2 class="text-2xl font-black sm:text-3xl md:text-4xl mt-2">
                    Comentarios de nuestros clientes
                </h2>
            </div>

            <!-- Desktop Grid / Mobile Carousel -->
            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="t in items"
                    :key="t.name"
                    class="relative rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur transition hover:border-primary/40 hover:bg-white/[0.08]"
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
                            <p class="font-bold text-sm">{{ t.name }}</p>
                            <p class="text-xs text-white/60">{{ t.city }}</p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
