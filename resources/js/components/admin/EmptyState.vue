<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        actionLabel?: string;
    }>(),
    {
        title: 'No se encontraron registros',
        description: 'Cuando crees tu primer registro aparecerá aquí.',
    },
);

defineEmits<{ action: [] }>();
</script>

<template>
    <div class="animate-fade-in flex flex-col items-center justify-center px-6 py-20 text-center">
        <div class="relative mb-5">
            <div class="absolute inset-0 rounded-2xl bg-primary/10 blur-2xl" />
            <div class="relative flex h-16 w-16 items-center justify-center rounded-2xl border border-border bg-card shadow-inner">
                <slot name="icon">
                    <Inbox class="h-7 w-7 text-muted-foreground" stroke-width="1.5" />
                </slot>
            </div>
        </div>
        <h3 class="text-base font-semibold text-foreground">{{ title }}</h3>
        <p class="mt-1.5 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <Button v-if="actionLabel" class="mt-6 rounded-xl" @click="$emit('action')">
            {{ actionLabel }}
        </Button>
    </div>
</template>
