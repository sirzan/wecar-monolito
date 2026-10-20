<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="`${title} · Admin`" />
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
        <div class="mt-8 grid place-items-center rounded-2xl border border-dashed border-border bg-card/50 py-20 text-center">
            <p class="text-sm text-muted-foreground">Módulo en construcción.</p>
        </div>
    </div>
</template>
