import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const STORAGE_KEY = 'wecar.favorites';

function readLocal(): string[] {
    if (typeof window === 'undefined') return [];

    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]');

        return Array.isArray(parsed) ? parsed.filter((value): value is string => typeof value === 'string') : [];
    } catch {
        return [];
    }
}

function writeLocal(ids: string[]): void {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch {
        // Sin almacenamiento disponible.
    }
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export const useFavoritesStore = defineStore('favorites', () => {
    const page = usePage<{ auth: { user: { id: string } | null }; favoriteIds?: string[] }>();
    const serverIds = Array.isArray(page.props.favoriteIds) ? page.props.favoriteIds : [];
    const ids = ref<string[]>([...new Set([...readLocal(), ...serverIds])]);
    const initialized = ref(false);
    const pending = ref<Set<string>>(new Set());

    const count = computed(() => ids.value.length);
    const isAuthenticated = computed(() => Boolean(page.props.auth.user));
    const has = (id: string) => ids.value.includes(id);

    const persist = () => {
        if (!isAuthenticated.value) {
            writeLocal(ids.value);
        }
    };

    const bootstrap = async (): Promise<void> => {
        if (initialized.value || typeof window === 'undefined') return;

        initialized.value = true;

        if (!isAuthenticated.value) return;

        const local = readLocal();

        try {
            if (local.length) {
                const response = await fetch('/api/favorites/sync', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({ ids: local }),
                });

                if (response.ok) {
                    const payload = await response.json();
                    ids.value = Array.isArray(payload.ids) ? payload.ids : ids.value;
                }
            }

            window.localStorage.removeItem(STORAGE_KEY);
        } catch {
            // Los favoritos del servidor siguen disponibles.
        }
    };

    const toggle = async (id: string): Promise<void> => {
        if (pending.value.has(id)) return;

        const wasFavorite = has(id);
        ids.value = wasFavorite ? ids.value.filter((current) => current !== id) : [...ids.value, id];
        persist();

        if (!isAuthenticated.value) {
            toast.success(wasFavorite ? 'Eliminado de favoritos.' : 'Auto agregado a favoritos.');
            return;
        }

        pending.value.add(id);

        try {
            const response = await fetch(`/api/favorites/${id}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
            });

            if (!response.ok) throw new Error('No se pudo guardar el favorito.');

            const payload = await response.json();
            ids.value = payload.favorited
                ? [...new Set([...ids.value, id])]
                : ids.value.filter((current) => current !== id);
        } catch {
            ids.value = wasFavorite ? [...new Set([...ids.value, id])] : ids.value.filter((current) => current !== id);
            toast.error('No pudimos actualizar tus favoritos. Intenta de nuevo.');
        } finally {
            pending.value.delete(id);
        }
    };

    const remove = (id: string) => {
        ids.value = ids.value.filter((current) => current !== id);
        persist();
    };

    return { ids, count, initialized, isAuthenticated, has, bootstrap, toggle, remove };
});
