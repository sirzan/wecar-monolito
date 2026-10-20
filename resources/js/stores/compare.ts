import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import { toast } from 'vue-sonner';

export interface CompareItem {
    id: string;
    title: string;
    image?: string | null;
    brand?: string | null;
    model?: string | null;
}

const STORAGE_KEY = 'wecar.compare';
const MAX_CARS = 3;

function readStoredItems(): CompareItem[] {
    if (typeof window === 'undefined') return [];

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        if (!Array.isArray(parsed)) return [];

        const items: CompareItem[] = [];

        for (const rawItem of parsed) {
            if (typeof rawItem === 'string') {
                items.push({ id: rawItem, title: 'Auto seleccionado' });
                continue;
            }

            if (!rawItem || typeof rawItem !== 'object') continue;

            const item = rawItem as Record<string, unknown>;

            if (typeof item.id !== 'string') continue;

            items.push({
                id: item.id,
                title: typeof item.title === 'string' ? item.title : 'Auto seleccionado',
                image: typeof item.image === 'string' ? item.image : null,
                brand: typeof item.brand === 'string' ? item.brand : null,
                model: typeof item.model === 'string' ? item.model : null,
            });
        }

        return items.slice(0, MAX_CARS);
    } catch {
        return [];
    }
}

export const useCompareStore = defineStore('compare', () => {
    const items = ref<CompareItem[]>(readStoredItems());

    watch(
        items,
        (value) => {
            if (typeof window === 'undefined') return;
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        },
        { deep: true },
    );

    const ids = computed(() => items.value.map((item) => item.id));
    const count = computed(() => items.value.length);
    const has = (id: string) => items.value.some((item) => item.id === id);

    const toggle = (item: CompareItem) => {
        if (has(item.id)) {
            items.value = items.value.filter((current) => current.id !== item.id);
            return;
        }

        if (items.value.length >= MAX_CARS) {
            toast.error(`Puedes comparar hasta ${MAX_CARS} autos.`);
            return;
        }

        items.value = [...items.value, item];
        toast.success('Auto agregado al comparador.');
    };

    const remove = (id: string) => {
        items.value = items.value.filter((item) => item.id !== id);
    };

    const clear = () => {
        items.value = [];
    };

    return { items, ids, count, has, toggle, remove, clear, max: MAX_CARS };
});
