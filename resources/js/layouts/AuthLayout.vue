<script setup lang="ts">
import { watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import Logo from '@/components/site/Logo.vue';

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
    <div class="grid min-h-screen place-items-center bg-gradient-hero px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex justify-center">
                <Logo />
            </div>
            <div class="rounded-3xl border border-border bg-card p-7 shadow-elegant sm:p-8">
                <slot />
            </div>
            <p class="mt-6 text-center text-xs text-muted-foreground">
                <Link href="/" class="hover:text-primary">← Volver al inicio</Link>
            </p>
        </div>
        <Toaster position="top-center" rich-colors />
    </div>
</template>
