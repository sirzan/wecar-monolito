<script setup lang="ts">
import { AlertCircle } from '@lucide/vue';

defineProps<{ fields: string[] }>();
</script>

<template>
    <div v-if="fields.length" role="alert" class="rounded-2xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
        <p class="flex items-center gap-1.5 font-bold">
            <AlertCircle class="h-3.5 w-3.5 shrink-0" />
            Revisa los campos marcados
        </p>
        <p class="mt-1 leading-relaxed">Falta completar: {{ fields.join(', ') }}.</p>
    </div>
</template>
