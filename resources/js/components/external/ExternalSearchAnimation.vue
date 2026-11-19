<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ExternalPlatform } from '@/types/external';

const props = defineProps<{ platforms: ExternalPlatform[] }>();

const activeIndex = ref(0);
const failedLogos = ref<Record<string, boolean>>({});
let timer: ReturnType<typeof setInterval> | null = null;

const activePlatform = computed(() => props.platforms[activeIndex.value] ?? null);
const logoSrc = (platform: ExternalPlatform) => `/images/platforms/${platform.id}.svg`;

const startRotation = () => {
    stopRotation();

    if (props.platforms.length < 2) return;

    timer = setInterval(() => {
        activeIndex.value = (activeIndex.value + 1) % props.platforms.length;
    }, 1600);
};

const stopRotation = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

watch(
    () => props.platforms,
    () => {
        activeIndex.value = 0;
        startRotation();
    },
    { deep: true },
);

onMounted(startRotation);
onBeforeUnmount(stopRotation);
</script>

<template>
    <div class="flex min-h-28 w-full flex-col items-center justify-center py-5" aria-label="Buscando en plataformas externas">
        <div class="relative grid h-24 w-full place-items-center overflow-hidden">
            <div
                v-if="activePlatform"
                class="pointer-events-none absolute h-16 w-16 rounded-full opacity-30 blur-2xl sm:h-20 sm:w-20"
                :style="{ backgroundColor: activePlatform.color }"
            />
            <template v-if="activePlatform">
                <span class="platform-ring pointer-events-none absolute h-20 w-20 rounded-full border sm:h-24 sm:w-24" :style="{ borderColor: `${activePlatform.color}55` }" />
                <span class="platform-ring platform-ring-delayed pointer-events-none absolute h-20 w-20 rounded-full border sm:h-24 sm:w-24" :style="{ borderColor: `${activePlatform.color}33` }" />
            </template>

            <Transition name="platform-reveal" mode="out-in">
                <div
                    v-if="activePlatform"
                    :key="activePlatform.id"
                    class="relative z-10 flex h-16 w-44 items-center justify-center sm:h-20 sm:w-56"
                >
                    <img
                        v-if="!failedLogos[activePlatform.id]"
                        :src="logoSrc(activePlatform)"
                        :alt="activePlatform.name"
                        class="max-h-11 max-w-full object-contain sm:max-h-14"
                        loading="lazy"
                        decoding="async"
                        @error="failedLogos[activePlatform.id] = true"
                    />
                    <span v-else class="text-center text-base font-black leading-tight text-secondary sm:text-lg">
                        {{ activePlatform.name }}
                    </span>
                </div>
            </Transition>
        </div>

        <p class="mt-1 text-center text-xs font-semibold text-muted-foreground" role="status" aria-live="polite">
            <template v-if="activePlatform">Buscando en {{ activePlatform.name }}…</template>
            <template v-else>Buscando en plataformas externas…</template>
        </p>
    </div>
</template>

<style scoped>
.platform-ring {
    animation: platform-pulse 1.6s ease-out infinite;
    will-change: transform, opacity;
}

.platform-ring-delayed {
    animation-delay: 0.8s;
}

.platform-reveal-enter-active,
.platform-reveal-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
    will-change: transform, opacity;
}

.platform-reveal-enter-from {
    opacity: 0;
    transform: scale(0.75) translateY(6px);
}

.platform-reveal-enter-to {
    opacity: 1;
    transform: scale(1.15) translateY(0);
}

.platform-reveal-leave-from {
    opacity: 1;
    transform: scale(1.15) translateY(0);
}

.platform-reveal-leave-to {
    opacity: 0;
    transform: scale(1.3) translateY(-4px);
}

@keyframes platform-pulse {
    0% {
        opacity: 0.55;
        transform: scale(0.8);
    }

    70% {
        opacity: 0;
        transform: scale(1.45);
    }

    100% {
        opacity: 0;
        transform: scale(1.45);
    }
}

@media (prefers-reduced-motion: reduce) {
    .platform-ring {
        animation: none;
        opacity: 0.25;
    }

    .platform-reveal-enter-active,
    .platform-reveal-leave-active {
        transition: opacity 0.2s ease;
    }

    .platform-reveal-enter-from,
    .platform-reveal-leave-to {
        opacity: 0;
        transform: none;
    }

    .platform-reveal-enter-to,
    .platform-reveal-leave-from {
        opacity: 1;
        transform: none;
    }
}
</style>
