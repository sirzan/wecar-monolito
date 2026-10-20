<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        as?: string;
        href?: string;
        variant?: 'default' | 'hero' | 'onPrimary' | 'secondary' | 'outline' | 'ghost' | 'link' | 'destructive';
        size?: 'sm' | 'default' | 'lg' | 'xl' | 'pill' | 'icon';
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        class?: string;
    }>(),
    {
        as: 'button',
        variant: 'default',
        size: 'default',
        type: 'button',
        disabled: false,
    },
);

const base =
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0';

const variants: Record<string, string> = {
    default: 'bg-primary text-primary-foreground hover:bg-primary/90',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
    secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    ghost: 'hover:bg-accent hover:text-accent-foreground',
    link: 'text-primary underline-offset-4 hover:underline',
    hero: 'bg-gradient-primary text-primary-foreground shadow-soft transition-all hover:-translate-y-0.5 hover:shadow-glow',
    onPrimary: 'bg-secondary text-secondary-foreground shadow-elegant hover:bg-secondary/90',
};

const sizes: Record<string, string> = {
    default: 'h-10 px-4 py-2',
    sm: 'h-9 rounded-md px-3',
    lg: 'h-11 rounded-md px-8',
    xl: 'h-14 rounded-full px-10 text-base',
    pill: 'h-10 rounded-full px-5',
    icon: 'h-10 w-10',
};

const classes = computed(() =>
    cn(base, variants[props.variant], sizes[props.size], props.class),
);
</script>

<template>
    <a v-if="as === 'a' && href" :href="href" :class="classes">
        <slot />
    </a>
    <button v-else :type="type" :disabled="disabled" :class="classes">
        <slot />
    </button>
</template>
