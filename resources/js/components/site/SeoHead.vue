<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        canonical?: string;
        image?: string | null;
        imageAlt?: string;
        type?: string;
        noindex?: boolean;
        robots?: string | null;
        price?: number | null;
        currency?: string;
        availability?: string | null;
        siteName?: string;
        locale?: string;
    }>(),
    {
        type: 'website',
        noindex: false,
        currency: 'MXN',
        siteName: 'Wecar',
        locale: 'es_MX',
    },
);

const robotsContent = () => props.robots ?? (props.noindex ? 'noindex,follow' : 'index,follow');
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <meta v-if="props.description" name="description" :content="props.description" />
        <link v-if="props.canonical" rel="canonical" :href="props.canonical" />
        <meta name="robots" :content="robotsContent()" />

        <meta property="og:title" :content="props.title" />
        <meta v-if="props.description" property="og:description" :content="props.description" />
        <meta property="og:type" :content="props.type" />
        <meta property="og:site_name" :content="props.siteName" />
        <meta property="og:locale" :content="props.locale" />
        <meta v-if="props.canonical" property="og:url" :content="props.canonical" />
        <meta v-if="props.image" property="og:image" :content="props.image" />
        <meta v-if="props.image && props.imageAlt" property="og:image:alt" :content="props.imageAlt" />
        <meta v-if="props.price != null" property="product:price:amount" :content="String(props.price)" />
        <meta v-if="props.price != null" property="product:price:currency" :content="props.currency" />
        <meta v-if="props.availability" property="og:availability" :content="props.availability" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="props.title" />
        <meta v-if="props.description" name="twitter:description" :content="props.description" />
        <meta v-if="props.image" name="twitter:image" :content="props.image" />
    </Head>
</template>
