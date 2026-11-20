<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        phrases: string[];
        typingSpeed?: number;
        deletingSpeed?: number;
        hold?: number;
        pause?: number;
        class?: string;
    }>(),
    {
        typingSpeed: 70,
        deletingSpeed: 35,
        hold: 800,
        pause: 400,
    },
);

const text = ref('');
const phraseIndex = ref(0);
const deleting = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let reducedMotion = false;

const stop = () => {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
};

const tick = () => {
    const phrases = props.phrases;

    if (!phrases.length) return;

    const phrase = phrases[phraseIndex.value] ?? '';

    if (!deleting.value) {
        if (text.value.length < phrase.length) {
            text.value = phrase.slice(0, text.value.length + 1);
            timer = setTimeout(tick, props.typingSpeed);
            return;
        }

        deleting.value = true;
        timer = setTimeout(tick, props.hold);
        return;
    }

    if (text.value.length > 0) {
        text.value = text.value.slice(0, -1);
        timer = setTimeout(tick, props.deletingSpeed);
        return;
    }

    deleting.value = false;
    phraseIndex.value = (phraseIndex.value + 1) % phrases.length;
    timer = setTimeout(tick, props.pause);
};

const start = () => {
    stop();

    if (!props.phrases.length) return;

    if (reducedMotion) {
        text.value = props.phrases[0];
        return;
    }

    if (!text.value) {
        phraseIndex.value = 0;
        deleting.value = false;
    }

    tick();
};

watch(
    () => props.phrases,
    () => {
        text.value = '';
        phraseIndex.value = 0;
        deleting.value = false;
        start();
    },
    { deep: true },
);

onMounted(() => {
    reducedMotion = typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    start();
});

onBeforeUnmount(stop);
</script>

<template>
    <span
        :class="cn('pointer-events-none absolute inset-y-0 left-0 flex min-w-0 items-center overflow-hidden pr-2 text-muted-foreground/60', props.class)"
        aria-hidden="true"
    >
        <span class="truncate">{{ text }}</span>
        <span class="typing-cursor ml-0.5 inline-block shrink-0 font-normal">|</span>
    </span>
</template>

<style scoped>
.typing-cursor {
    animation: typing-blink 1s steps(1) infinite;
}

@keyframes typing-blink {
    0%,
    49% {
        opacity: 1;
    }

    50%,
    100% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .typing-cursor {
        animation: none;
        opacity: 1;
    }
}
</style>
