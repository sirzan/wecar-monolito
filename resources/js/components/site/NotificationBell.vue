<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, Check, CheckCheck } from '@lucide/vue';

interface NotificationItem {
    id: string;
    title: string;
    message: string;
    link: string | null;
    type: string;
    read: boolean;
    created_at: string;
}

const open = ref(false);
const loading = ref(false);
const notifications = ref<NotificationItem[]>([]);
const root = ref<HTMLElement | null>(null);
let timer: ReturnType<typeof setInterval> | null = null;

const unread = computed(() => notifications.value.filter((item) => !item.read).length);

const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const load = async () => {
    loading.value = true;

    try {
        const response = await fetch('/api/notifications', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        const data = await response.json();
        notifications.value = data.notifications ?? [];
    } catch {
        // Sin conexión o sin sesión.
    } finally {
        loading.value = false;
    }
};

const markRead = async (item: NotificationItem) => {
    if (!item.read) {
        item.read = true;
        await fetch(`/api/notifications/${item.id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
        });
    }

    if (item.link) {
        open.value = false;
        router.visit(item.link);
    }
};

const markAll = async () => {
    notifications.value = notifications.value.map((item) => ({ ...item, read: true }));

    await fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
    });
};

const timeAgo = (value: string) => {
    const diff = Math.max(0, Date.now() - new Date(value).getTime());
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) return 'Ahora';
    if (minutes < 60) return `Hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours} h`;
    const days = Math.floor(hours / 24);
    return `Hace ${days} d`;
};

const onDocumentClick = (event: MouseEvent) => {
    if (!root.value?.contains(event.target as Node)) open.value = false;
};

onMounted(() => {
    void load();
    timer = setInterval(load, 60000);
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            aria-label="Notificaciones"
            class="relative grid h-10 w-10 place-items-center rounded-full bg-accent text-primary transition hover:bg-accent/80"
            @click.stop="open = !open"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unread"
                class="absolute -right-0.5 -top-0.5 grid min-h-[18px] min-w-[18px] place-items-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-destructive-foreground"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-border bg-popover shadow-elegant"
        >
            <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-foreground">Notificaciones</p>
                    <p class="text-xs text-muted-foreground">
                        {{ unread ? `${unread} sin leer` : 'Todo al día' }}
                    </p>
                </div>
                <button
                    v-if="unread"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-accent"
                    @click.stop="markAll"
                >
                    <CheckCheck class="h-3.5 w-3.5" /> Leer todas
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <div v-if="loading && !notifications.length" class="px-4 py-8 text-center text-xs text-muted-foreground">
                    Cargando…
                </div>
                <ul v-else-if="notifications.length" class="divide-y divide-border/60">
                    <li v-for="item in notifications" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-accent/50"
                            :class="!item.read ? 'bg-accent/30' : ''"
                            @click="markRead(item)"
                        >
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-primary'" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-foreground">{{ item.title }}</span>
                                <span class="line-clamp-2 text-xs text-muted-foreground">{{ item.message }}</span>
                                <span class="mt-1 block text-[10px] uppercase tracking-wide text-muted-foreground/70">{{ timeAgo(item.created_at) }}</span>
                            </span>
                            <Check v-if="!item.read" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        </button>
                    </li>
                </ul>
                <div v-else class="px-4 py-10 text-center">
                    <Bell class="mx-auto mb-2 h-8 w-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No tienes notificaciones</p>
                </div>
            </div>

            <div class="border-t border-border/60 px-4 py-2.5">
                <a href="/panel/configuracion" class="text-xs font-semibold text-primary hover:underline">
                    Configurar notificaciones
                </a>
            </div>
        </div>
    </div>
</template>
