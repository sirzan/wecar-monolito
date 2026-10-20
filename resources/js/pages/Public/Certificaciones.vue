<script setup lang="ts">
import SeoHead from '@/components/site/SeoHead.vue';
import { BadgeCheck, ShieldCheck, Wrench } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{ seo: { title: string; description: string; canonical: string } }>();

const points = [
    { icon: Wrench, title: 'Inspección mecánica', description: 'Más de 100 puntos de revisión mecánica.' },
    { icon: ShieldCheck, title: 'Revisión legal', description: 'Documentos y historial verificados.' },
    { icon: BadgeCheck, title: 'Certificado Wecar', description: 'Sello de calidad para autos aprobados.' },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <section class="bg-gradient-hero px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <h1 class="page-title text-secondary">Certificación de 160 puntos</h1>
            <p class="page-subtitle mt-4 max-w-2xl text-muted-foreground">
                Cada auto aprobado pasa por una inspección mecánica y legal exhaustiva.
            </p>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div v-for="point in points" :key="point.title" class="rounded-2xl border border-border bg-card p-6 shadow-soft">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent text-accent-foreground">
                    <component :is="point.icon" class="h-5 w-5" />
                </span>
                <h2 class="mt-5 font-bold text-secondary">{{ point.title }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">{{ point.description }}</p>
            </div>
        </div>
    </section>
</template>
