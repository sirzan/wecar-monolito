<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        canonical?: string;
        image?: string | null;
        type?: string;
        noindex?: boolean;
    }>(),
    {
        type: 'website',
        noindex: false,
    },
);
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <meta v-if="props.description" name="description" :content="props.description" />
        <link v-if="props.canonical" rel="canonical" :href="props.canonical" />
        <meta name="robots" :content="props.noindex ? 'noindex,follow' : 'index,follow'" />

        <meta property="og:title" :content="props.title" />
        <meta v-if="props.description" property="og:description" :content="props.description" />
        <meta property="og:type" :content="props.type" />
        <meta v-if="props.canonical" property="og:url" :content="props.canonical" />
        <meta v-if="props.image" property="og:image" :content="props.image" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="props.title" />
        <meta v-if="props.description" name="twitter:description" :content="props.description" />
        <meta v-if="props.image" name="twitter:image" :content="props.image" />
    </Head>
</template>
