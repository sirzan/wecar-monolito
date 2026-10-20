<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Download, X } from '@lucide/vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const visible = ref(false);
const deferred = ref<BeforeInstallPromptEvent | null>(null);
let timeout: ReturnType<typeof setTimeout> | null = null;

const KEY = 'wecar.install.dismissed';

const close = () => {
    visible.value = false;
    window.localStorage.setItem(KEY, String(Date.now()));
};

const install = async () => {
    if (!deferred.value) return;

    await deferred.value.prompt();
    const choice = await deferred.value.userChoice;

    if (choice.outcome === 'accepted') {
        window.localStorage.setItem(KEY, String(Date.now()));
    }

    visible.value = false;
    deferred.value = null;
};

const onPrompt = (event: Event) => {
    event.preventDefault();
    deferred.value = event as BeforeInstallPromptEvent;

    const dismissedAt = Number(window.localStorage.getItem(KEY) ?? 0);
    const sevenDays = 7 * 24 * 60 * 60 * 1000;

    if (Date.now() - dismissedAt < sevenDays) return;

    timeout = setTimeout(() => {
        visible.value = true;
    }, 12000);
};

onMounted(() => {
    window.addEventListener('beforeinstallprompt', onPrompt);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onPrompt);
    if (timeout) clearTimeout(timeout);
});
</script>

<template>
    <div
        v-if="visible && deferred"
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
