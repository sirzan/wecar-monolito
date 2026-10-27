<script setup lang="ts">
import { ref } from 'vue';
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

const isPaused = ref(false);
const failedPartners = ref<Record<string, boolean>>({});
</script>

<template>
    <div
        :class="cn('partners-viewport relative w-full overflow-hidden', props.class)"
        aria-label="Bancos y financieras participantes"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
    >
        <div class="partners-track" :style="{ animationPlayState: isPaused ? 'paused' : 'running' }">
            <div
                v-for="groupIndex in 2"
                :key="groupIndex"
                class="partners-group"
                :aria-hidden="groupIndex === 2 ? 'true' : undefined"
            >
                <div
                    v-for="(partner, idx) in partners"
                    :key="`${groupIndex}-${partner.name}-${idx}`"
                    class="partner-slot"
                >
                    <div
                        class="flex h-12 w-full items-center justify-center rounded-xl border px-3 transition-transform hover:scale-[1.02] sm:h-14"
                        :class="
                            inverse
                                ? 'border-white/15 bg-white/95 text-secondary shadow-sm backdrop-blur-xs'
                                : 'border-border bg-card text-secondary shadow-soft'
                        "
                    >
                        <img
                            v-if="!failedPartners[`${groupIndex}-${idx}`]"
                            :src="partner.logo"
                            :alt="partner.name"
                            class="h-8 w-full object-contain sm:h-9"
                            loading="lazy"
                            @error="failedPartners[`${groupIndex}-${idx}`] = true"
                        />
                        <span v-else class="text-sm font-black text-secondary">{{ partner.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.partners-viewport {
    --visible: 2.5;
    --gap: 0.75rem;
    --card-width: calc(100cqw / var(--visible));
    container-type: inline-size;
}

.partners-track {
    display: flex;
    width: calc(var(--card-width) * 20);
    animation: partners-step 30s ease-in-out infinite;
    will-change: transform;
}

.partners-group {
    display: flex;
}

.partner-slot {
    flex: 0 0 var(--card-width);
    padding: 0 calc(var(--gap) / 2);
}

@keyframes partners-step {
    0%,
    8% {
        transform: translateX(0);
    }

    10%,
    18% {
        transform: translateX(calc(var(--card-width) * -1));
    }

    20%,
    28% {
        transform: translateX(calc(var(--card-width) * -2));
    }

    30%,
    38% {
        transform: translateX(calc(var(--card-width) * -3));
    }

    40%,
    48% {
        transform: translateX(calc(var(--card-width) * -4));
    }

    50%,
    58% {
        transform: translateX(calc(var(--card-width) * -5));
    }

    60%,
    68% {
        transform: translateX(calc(var(--card-width) * -6));
    }

    70%,
    78% {
        transform: translateX(calc(var(--card-width) * -7));
    }

    80%,
    88% {
        transform: translateX(calc(var(--card-width) * -8));
    }

    90%,
    98% {
        transform: translateX(calc(var(--card-width) * -9));
    }

    100% {
        transform: translateX(calc(var(--card-width) * -10));
    }
}

@media (min-width: 640px) {
    .partners-viewport {
        --visible: 3;
        --gap: 1rem;
    }
}

@media (min-width: 1024px) {
    .partners-viewport {
        --visible: 5;
    }
}

@media (prefers-reduced-motion: reduce) {
    .partners-track {
        animation: none;
    }
}
</style>
