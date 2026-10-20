<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { cn } from '@/lib/utils';

interface Partner {
    name: string;
    logo: string;
}

const props = withDefaults(
    defineProps<{
        class?: string;
        inverse?: boolean;
    }>(),
    {
        class: '',
        inverse: false,
    },
);

const partners: Partner[] = [
    { name: 'BBVA', logo: '/images/financial-partners/bbva.png' },
    { name: 'Santander', logo: '/images/financial-partners/santander.png' },
    { name: 'HSBC', logo: '/images/financial-partners/hsbc.png' },
    { name: 'Banorte', logo: '/images/financial-partners/banorte.png' },
    { name: 'Scotiabank', logo: '/images/financial-partners/scotiabank.png' },
    { name: 'Afirme', logo: '/images/financial-partners/afirme.png' },
    { name: 'Banregio', logo: '/images/financial-partners/banregio.png' },
    { name: 'Citibanamex', logo: '/images/financial-partners/banamex.png' },
    { name: 'Inbursa', logo: '/images/financial-partners/inbursa.png' },
    { name: 'CrediClub', logo: '/images/financial-partners/crediclub.png' },
];

// Duplicamos para un marquee continuo sin saltos visuales
const duplicatedPartners = computed(() => [...partners, ...partners, ...partners]);
const isPaused = ref(false);
const failedPartners = ref<Record<number, boolean>>({});
</script>

<template>
    <div
        :class="cn('group relative w-full overflow-hidden', props.class)"
        aria-label="Bancos y financieras participantes"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
    >
        <!-- Degradados en los bordes para suavizar la entrada/salida -->
        <div
            class="pointer-events-none absolute inset-y-0 left-0 z-10 w-8 bg-gradient-to-r sm:w-16"
            :class="inverse ? 'from-secondary to-transparent' : 'from-background to-transparent'"
        />
        <div
            class="pointer-events-none absolute inset-y-0 right-0 z-10 w-8 bg-gradient-to-l sm:w-16"
            :class="inverse ? 'from-secondary to-transparent' : 'from-background to-transparent'"
        />

        <!-- Contenedor del marquee deslizante continuo -->
        <div
            class="flex w-max items-center gap-3 py-1 will-change-transform animate-partners-marquee"
            :style="{ animationPlayState: isPaused ? 'paused' : 'running' }"
        >
            <div
                v-for="(partner, idx) in duplicatedPartners"
                :key="`${partner.name}-${idx}`"
                class="flex h-12 w-36 shrink-0 items-center justify-center rounded-xl border px-3 transition-transform hover:scale-[1.02] sm:h-14 sm:w-44"
                :class="
                    inverse
                        ? 'border-white/15 bg-white/95 text-secondary shadow-sm backdrop-blur-xs'
                        : 'border-border bg-card text-secondary shadow-soft'
                "
            >
                <img
                    v-if="!failedPartners[idx]"
                    :src="partner.logo"
                    :alt="partner.name"
                    class="h-7 w-full object-contain sm:h-8"
                    loading="lazy"
                    @error="failedPartners[idx] = true"
                />
                <span v-else class="text-xs font-black text-secondary">{{ partner.name }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes partners-marquee {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(calc(-100% / 3));
    }
}

.animate-partners-marquee {
    animation: partners-marquee 24s linear infinite;
}

@media (max-width: 640px) {
    .animate-partners-marquee {
        animation-duration: 18s;
    }
}
</style>