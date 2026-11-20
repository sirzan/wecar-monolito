<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Download, X } from '@lucide/vue';
import { useInstallPrompt } from '@/composables/useInstallPrompt';

const { canInstall, install: promptInstall } = useInstallPrompt();

const visible = ref(false);
let timeout: ReturnType<typeof setTimeout> | null = null;

const KEY = 'wecar.install.dismissed';
const ONE_HOUR = 60 * 60 * 1000;

const cooldownPassed = () => {
    const dismissedAt = Number(window.localStorage.getItem(KEY) ?? 0);

    return Date.now() - dismissedAt >= ONE_HOUR;
};

const schedule = () => {
    if (timeout) clearTimeout(timeout);

    timeout = setTimeout(() => {
        if (canInstall.value && cooldownPassed()) {
            visible.value = true;
        }
    }, 12000);
};

const close = () => {
    visible.value = false;
    window.localStorage.setItem(KEY, String(Date.now()));
};

const install = async () => {
    const accepted = await promptInstall();

    if (accepted) {
        window.localStorage.setItem(KEY, String(Date.now()));
    }

    visible.value = false;
};

watch(canInstall, (value) => {
    if (value) {
        schedule();
    }
});

onMounted(schedule);

onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout);
});
</script>

<template>
    <div
        v-if="visible && canInstall"
        class="bottom-offset animate-slide-up fixed left-4 z-40 w-[min(320px,calc(100vw-2rem))] rounded-2xl border border-border bg-card p-4 shadow-elegant"
    >
        <button
            type="button"
            aria-label="Cerrar"
            class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full text-muted-foreground hover:bg-accent"
            @click="close"
        >
            <X class="h-3.5 w-3.5" />
        </button>

        <div class="flex items-start gap-3">
            <img src="/web-app-manifest-192x192.png" alt="Wecar" class="h-11 w-11 rounded-xl" />
            <div class="min-w-0">
                <p class="text-sm font-black text-secondary">Instala Wecar en tu celular</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Accede más rápido y recibe avisos de tus autos y trámites.
                </p>
            </div>
        </div>

        <button
            type="button"
            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-xs font-bold text-primary-foreground"
            @click="install"
        >
            <Download class="h-4 w-4" /> Instalar app
        </button>
    </div>
</template>
