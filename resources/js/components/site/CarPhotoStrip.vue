<script setup lang="ts">
import { ref } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    slides: string[];
    alt: string;
    class?: string;
}>();

const emit = defineEmits<{ open: [index: number] }>();

const active = ref(0);
const track = ref<HTMLElement | null>(null);

const onScroll = () => {
    if (!track.value) return;
    const width = track.value.clientWidth;
    if (!width) return;
    active.value = Math.round(track.value.scrollLeft / width);
};

const goTo = (index: number) => {
    if (!track.value) return;
    track.value.scrollTo({ left: track.value.clientWidth * index, behavior: 'smooth' });
};
</script>

<template>
    <div :class="cn('relative overflow-hidden bg-muted', props.class)">
        <div
            ref="track"
            class="wc-no-scrollbar flex h-full w-full snap-x snap-mandatory overflow-x-auto"
            @scroll.passive="onScroll"
        >
            <button
                v-for="(slide, index) in slides"
                :key="`${slide}-${index}`"
                type="button"
                class="h-full w-full shrink-0 snap-center"
                @click="emit('open', index)"
            >
                <img
                    :src="slide"
                    :alt="`${alt} ${index + 1}`"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover"
                />
            </button>
        </div>

        <div
            v-if="slides.length > 1"
            class="pointer-events-none absolute inset-x-0 bottom-2 flex justify-center gap-1"
        >
            <button
                v-for="(_, index) in slides"
                :key="index"
                type="button"
                :aria-label="`Ir a foto ${index + 1}`"
                class="pointer-events-auto h-1.5 rounded-full transition-all"
                :class="index === active ? 'w-4 bg-primary' : 'w-1.5 bg-white/70'"
                @click.stop="goTo(index)"
            />
        </div>

        <slot />
    </div>
</template>
