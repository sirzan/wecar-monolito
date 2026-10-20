<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        variant?: 'default' | 'secondary' | 'destructive' | 'outline' | 'success';
        class?: string;
    }>(),
    { variant: 'default' },
);

const variants: Record<string, string> = {
    default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
    secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
    destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
    outline: 'text-foreground',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
        variants[props.variant],
        props.class,
    ),
);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
