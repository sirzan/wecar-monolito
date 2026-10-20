<script setup lang="ts">
import SeoHead from '@/components/site/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{ settings?: { title?: string; effective_date?: string; content?: string }; seo: { title: string; description: string; canonical: string } }>();

</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <h1 class="text-3xl font-black text-secondary sm:text-4xl">{{ settings?.title || 'Términos y Condiciones de Uso — WeCar' }}</h1>
        <p class="mt-2 text-sm text-muted-foreground"><strong>Versión 2.1</strong> — Efectiva desde: {{ settings?.effective_date || '15/10/2025' }}</p>
        <div v-if="settings?.content" class="prose prose-sm mt-10 max-w-none text-secondary/80" v-html="settings.content" />
        <div v-else class="mt-10 space-y-4 text-sm leading-relaxed text-secondary/80">
            <p>
                Al acceder y utilizar la plataforma WeCar, usted acepta estar sujeto a estos Términos
                y Condiciones de Uso, así como a nuestra Política de Privacidad.
            </p>
            <h2 class="mt-8 text-lg font-black text-secondary">1. Descripción del servicio</h2>
            <p>
                WeCar es una plataforma digital que conecta compradores y vendedores de autos
                seminuevos en México, facilitando transacciones seguras y transparentes.
            </p>
            <h2 class="mt-8 text-lg font-black text-secondary">2. Elegibilidad y registro</h2>
            <p>
                Para utilizar nuestros servicios debe ser mayor de 18 años y proporcionar información
                veraz y actualizada.
            </p>
            <h2 class="mt-8 text-lg font-black text-secondary">3. Uso de la plataforma</h2>
            <p>
                El usuario se compromete a no publicar información falsa ni contenido que infrinja
                derechos de terceros.
            </p>
        </div>
    </section>
</template>
