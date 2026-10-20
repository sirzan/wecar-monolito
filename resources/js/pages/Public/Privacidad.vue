<script setup lang="ts">
import SeoHead from '@/components/site/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{ settings?: { title?: string; effective_date?: string; content?: string }; seo: { title: string; description: string; canonical: string } }>();

</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <h1 class="text-3xl font-black text-secondary sm:text-4xl">{{ settings?.title || 'Aviso de Privacidad — WeCar' }}</h1>
        <p class="mt-2 text-sm text-muted-foreground"><strong>Versión 2.1</strong> — Efectiva desde: {{ settings?.effective_date || '15/10/2025' }}</p>
        <div v-if="settings?.content" class="prose prose-sm mt-10 max-w-none text-secondary/80" v-html="settings.content" />
        <div v-else class="mt-10 space-y-4 text-sm leading-relaxed text-secondary/80">
            <p>
                WeCar respeta su privacidad y protege sus datos personales conforme a la Ley Federal
                de Protección de Datos Personales en Posesión de los Particulares.
            </p>
            <h2 class="mt-8 text-lg font-black text-secondary">Datos que recabamos</h2>
            <p>
                Recabamos datos de identificación, contacto y financieros necesarios para prestar
                nuestros servicios de compra, venta y financiamiento.
            </p>
            <h2 class="mt-8 text-lg font-black text-secondary">Finalidades</h2>
            <p>
                Sus datos se utilizan para gestionar su cuenta, procesar solicitudes y mejorar
                nuestros servicios.
            </p>
        </div>
    </section>
</template>
