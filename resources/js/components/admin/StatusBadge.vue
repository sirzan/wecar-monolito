<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'primary';

const props = withDefaults(defineProps<{ label: string; tone?: Tone; class?: string }>(), {
    tone: 'neutral',
});

const tones: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground border-border',
    success: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    warning: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    danger: 'bg-destructive/10 text-destructive border-destructive/30',
    primary: 'bg-primary/10 text-primary border-primary/30',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium leading-tight',
        tones[props.tone],
        props.class,
    ),
);
</script>

<template>
    <span :class="classes">
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
        {{ label }}
    </span>
</template>
