<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string | number>();
defineProps<{
    type?: string;
    placeholder?: string;
    class?: string;
    required?: boolean;
    autocomplete?: string;
    disabled?: boolean;
}>();
</script>

<template>
    <input
        v-model="model"
        :type="type ?? 'text'"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        :disabled="disabled"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                $props.class,
            )
        "
    />
</template>
