<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Bell, Loader2, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import { usePushNotifications } from '@/composables/usePush';

const page = usePage<{
    auth: { user: { id: string } | null };
    push: { publicKey: string | null };
}>();

const push = usePushNotifications();
const visible = ref(false);
let timeout: ReturnType<typeof setTimeout> | null = null;

const KEY = 'wecar.push.prompt.dismissed';
const SEVEN_DAYS = 7 * 24 * 60 * 60 * 1000;

const eligible = computed(() => {
    if (!page.props.auth?.user || !push.supported.value || !page.props.push?.publicKey) {
        return false;
    }

    return typeof Notification !== 'undefined' && Notification.permission === 'default';
});

const close = () => {
    visible.value = false;
    window.localStorage.setItem(KEY, String(Date.now()));
};

const activate = async () => {
    await push.subscribe();
    visible.value = false;

    if (push.subscribed.value) {
        window.localStorage.setItem(KEY, String(Date.now()));
    }
};

onMounted(() => {
    if (!eligible.value) return;

    const dismissedAt = Number(window.localStorage.getItem(KEY) ?? 0);

    if (Date.now() - dismissedAt < SEVEN_DAYS) return;

    timeout = setTimeout(() => {
        if (eligible.value) {
            visible.value = true;
        }
    }, 8000);
});

onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout);
});
</script>

<template>
    <div
        v-if="visible"
        class="bottom-offset fixed right-4 z-40 w-[min(320px,calc(100vw-2rem))] rounded-2xl border border-border bg-card p-4 shadow-elegant"
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
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <Bell class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-black text-secondary">Activa las notificaciones</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Recibe avisos de tus autos, trámites y pagos.
                </p>
            </div>
        </div>

        <div class="mt-3 grid gap-2">
            <Button
                variant="hero"
                class="h-9 w-full gap-2 rounded-full text-xs font-bold"
                :disabled="push.loading.value"
                @click="activate"
            >
                <Loader2 v-if="push.loading.value" class="h-4 w-4 animate-spin" />
                <Bell v-else class="h-4 w-4" />
                Activar notificaciones
            </Button>
            <button type="button" class="text-xs font-semibold text-muted-foreground hover:text-secondary" @click="close">
                Ahora no
            </button>
        </div>
    </div>
</template>
