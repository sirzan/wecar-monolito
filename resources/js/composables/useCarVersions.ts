import { onBeforeUnmount, ref } from 'vue';

export interface CarVersionOption {
    id: string;
    name: string;
}

export interface CarVersionQuery {
    brandId?: string | null;
    modelId?: string | null;
    brand?: string | null;
    model?: string | null;
    year?: number | null;
    force?: boolean;
}

interface UseCarVersionsOptions {
    fast?: boolean;
    debounceMs?: number;
}

const versionCache = new Map<string, CarVersionOption[]>();

export function useCarVersions(options: UseCarVersionsOptions = {}) {
    const fast = options.fast ?? false;
    const debounceMs = options.debounceMs ?? 150;

    const versions = ref<CarVersionOption[]>([]);
    const loading = ref(false);
    const error = ref('');

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let sequence = 0;

    const cacheKeyFor = (query: CarVersionQuery): string => {
        const identity = query.modelId
            ? `id:${query.modelId}`
            : `name:${(query.brand ?? '').trim().toLowerCase()}|${(query.model ?? '').trim().toLowerCase()}`;

        return `${fast ? 'fast' : 'full'}:${identity}:${query.year ?? ''}`;
    };

    const cancelPending = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (controller) {
            controller.abort();
            controller = null;
        }
    };

    const reset = () => {
        cancelPending();
        sequence += 1;
        versions.value = [];
        loading.value = false;
        error.value = '';
    };

    const load = async (query: CarVersionQuery): Promise<CarVersionOption[]> => {
        const hasModel = Boolean(query.modelId || (query.brand && query.model));

        if (!hasModel || !query.year) {
            reset();
            return [];
        }

        const key = cacheKeyFor(query);

        if (!query.force && versionCache.has(key)) {
            cancelPending();
            const cached = versionCache.get(key) ?? [];
            versions.value = cached;
            loading.value = false;
            error.value = '';
            return cached;
        }

        cancelPending();
        const currentSequence = ++sequence;
        controller = new AbortController();
        loading.value = true;
        error.value = '';

        try {
            const params = new URLSearchParams({
                year: String(query.year),
            });

            if (query.modelId) {
                params.set('model_id', query.modelId);
                if (query.brandId) {
                    params.set('brand_id', query.brandId);
                }
            } else {
                if (query.brand) params.set('brand', query.brand);
                if (query.model) params.set('model', query.model);
            }

            if (fast) {
                params.set('fast', '1');
            }

            const response = await fetch(`/api/car-versions?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.error || 'No pudimos cargar las versiones.');
            }

            if (currentSequence !== sequence) {
                return versions.value;
            }

            const list: CarVersionOption[] = Array.isArray(payload.versions) ? payload.versions : [];
            versions.value = list;
            versionCache.set(key, list);

            return list;
        } catch (err) {
            if ((err as Error)?.name === 'AbortError' || currentSequence !== sequence) {
                return versions.value;
            }

            versions.value = [];
            error.value = err instanceof Error ? err.message : 'No pudimos cargar las versiones.';

            return [];
        } finally {
            if (currentSequence === sequence) {
                loading.value = false;
            }
        }
    };

    const scheduleLoad = (query: CarVersionQuery) => {
        const hasModel = Boolean(query.modelId || (query.brand && query.model));

        if (!hasModel || !query.year) {
            reset();
            return;
        }

        const key = cacheKeyFor(query);

        if (!query.force && versionCache.has(key)) {
            void load(query);
            return;
        }

        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            void load(query);
        }, debounceMs);
    };

    onBeforeUnmount(() => {
        cancelPending();
    });

    return {
        versions,
        loading,
        error,
        load,
        scheduleLoad,
        reset,
    };
}
