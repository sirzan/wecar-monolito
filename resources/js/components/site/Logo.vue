<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{ variant?: 'default' | 'light'; class?: string }>(), {
    variant: 'default',
});
</script>

<template>
    <Link href="/" :class="cn('inline-flex items-center', props.class)" aria-label="Wecar inicio">
        <img
            v-if="variant === 'default'"
            src="/images/wecar-logo.png"
            alt="Wecar"
            class="logo-silhouette h-11 w-auto select-none sm:h-12"
            draggable="false"
        />
        <img
            v-else
            src="/images/wecar-logo-light.png"
            alt="Wecar"
            class="logo-silhouette h-11 w-auto select-none sm:h-12"
            draggable="false"
        />
    </Link>
</template>
