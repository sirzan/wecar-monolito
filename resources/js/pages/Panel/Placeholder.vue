<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';

defineOptions({ layout: PanelLayout });

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="mx-auto max-w-5xl">
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">{{ title }}</h1>
        <div class="mt-6 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <p class="text-sm text-muted-foreground">Esta sección estará disponible en la siguiente fase.</p>
        </div>
    </div>
</template>
