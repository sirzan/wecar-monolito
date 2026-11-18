import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const STORAGE_KEY = 'wecar.favorites';
const EXTERNAL_STORAGE_KEY = 'wecar.favorites.external';

function readLocal(key = STORAGE_KEY): string[] {
    if (typeof window === 'undefined') return [];

    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(key) ?? '[]');

        return Array.isArray(parsed) ? parsed.filter((value): value is string => typeof value === 'string') : [];
    } catch {
        return [];
    }
}

function writeLocal(ids: string[], key = STORAGE_KEY): void {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(key, JSON.stringify(ids));
    } catch {
        // Sin almacenamiento disponible.
    }
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export const useFavoritesStore = defineStore('favorites', () => {
    const page = usePage<{ auth: { user: { id: string } | null }; favoriteIds?: string[]; externalFavoriteIds?: string[] }>();
    const serverIds = Array.isArray(page.props.favoriteIds) ? page.props.favoriteIds : [];
    const serverExternalIds = Array.isArray(page.props.externalFavoriteIds) ? page.props.externalFavoriteIds : [];
    const ids = ref<string[]>([...new Set([...readLocal(), ...serverIds])]);
    const externalIds = ref<string[]>([...new Set([...readLocal(EXTERNAL_STORAGE_KEY), ...serverExternalIds])]);
    const initialized = ref(false);
    const pending = ref<Set<string>>(new Set());

    const count = computed(() => ids.value.length + externalIds.value.length);
    const isAuthenticated = computed(() => Boolean(page.props.auth.user));
    const has = (id: string) => ids.value.includes(id);
    const hasExternal = (id: string) => externalIds.value.includes(id);

    const persist = () => {
        if (!isAuthenticated.value) {
            writeLocal(ids.value);
        }
    };

    const persistExternal = () => {
        if (!isAuthenticated.value) {
            writeLocal(externalIds.value, EXTERNAL_STORAGE_KEY);
        }
    };

    const bootstrap = async (): Promise<void> => {
        if (initialized.value || typeof window === 'undefined') return;

        initialized.value = true;

        if (!isAuthenticated.value) return;

        const local = readLocal();
        const localExternal = readLocal(EXTERNAL_STORAGE_KEY);

        try {
            if (local.length || localExternal.length) {
                const response = await fetch('/api/favorites/sync', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({ ids: local, external_ids: localExternal }),
                });

                if (response.ok) {
                    const payload = await response.json();
                    ids.value = Array.isArray(payload.ids) ? payload.ids : ids.value;
                    externalIds.value = Array.isArray(payload.externalIds) ? payload.externalIds : externalIds.value;
                }
            }

            window.localStorage.removeItem(STORAGE_KEY);
            window.localStorage.removeItem(EXTERNAL_STORAGE_KEY);
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

    const toggleExternal = async (id: string): Promise<void> => {
        if (pending.value.has(id)) return;

        const wasFavorite = hasExternal(id);
        externalIds.value = wasFavorite
            ? externalIds.value.filter((current) => current !== id)
            : [...externalIds.value, id];
        persistExternal();

        if (!isAuthenticated.value) {
            toast.success(wasFavorite ? 'Eliminado de favoritos.' : 'Auto externo agregado a favoritos.');
            return;
        }

        pending.value.add(id);

        try {
            const response = await fetch(`/api/favorites/external/${id}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
            });

            if (!response.ok) throw new Error('No se pudo guardar el favorito.');

            const payload = await response.json();
            externalIds.value = payload.favorited
                ? [...new Set([...externalIds.value, id])]
                : externalIds.value.filter((current) => current !== id);
        } catch {
            externalIds.value = wasFavorite
                ? [...new Set([...externalIds.value, id])]
                : externalIds.value.filter((current) => current !== id);
            toast.error('No pudimos actualizar tus favoritos. Intenta de nuevo.');
        } finally {
            pending.value.delete(id);
        }
    };

    const removeExternal = (id: string) => {
        externalIds.value = externalIds.value.filter((current) => current !== id);
        persistExternal();
    };

    return { ids, externalIds, count, initialized, isAuthenticated, has, hasExternal, bootstrap, toggle, toggleExternal, remove, removeExternal };
});
