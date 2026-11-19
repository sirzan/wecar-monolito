<script setup lang="ts">
import { AlertCircle } from '@lucide/vue';

defineProps<{ message?: string | null }>();
</script>

<template>
    <p v-if="message" class="mt-1 flex items-start gap-1 text-xs font-medium text-destructive">
        <AlertCircle class="mt-0.5 h-3 w-3 shrink-0" />
        <span>{{ message }}</span>
    </p>
</template>
