<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import Navbar from '@/components/site/Navbar.vue';
import Footer from '@/components/site/Footer.vue';
import CompareBar from '@/components/site/CompareBar.vue';
import MobileBottomNav from '@/components/site/MobileBottomNav.vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="has-bottom-nav flex min-h-screen flex-col bg-background">
        <Navbar />
        <main class="flex-1">
            <slot />
        </main>
        <Footer />
        <CompareBar />
        <MobileBottomNav />
        <Toaster position="top-center" rich-colors />
    </div>
</template>
