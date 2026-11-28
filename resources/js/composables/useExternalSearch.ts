import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    ExternalCar,
    ExternalResolveResponse,
    ExternalSearchCriteria,
    ExternalSearchResponse,
    ExternalSearchStatus,
} from '@/types/external';

export type { ExternalSearchCriteria };

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers ?? {});
    headers.set('Accept', 'application/json');

    if (method !== 'GET') {
        headers.set('Content-Type', 'application/json');
        headers.set('X-Requested-With', 'XMLHttpRequest');
        headers.set('X-CSRF-TOKEN', csrfToken());
    }

    const response = await fetch(url, { ...options, headers });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        throw new Error(payload?.message ?? payload?.error ?? 'No se pudo completar la solicitud.');
    }

    return response.json() as Promise<T>;
}

export function useExternalSearch() {
    const status = ref<ExternalSearchStatus | null>(null);
    const results = ref<ExternalCar[]>([]);
    const platformsSearched = ref<string[]>([]);
    const cachedCars = ref<ExternalCar[]>([]);
    const loading = ref(false);
    const loadingCached = ref(false);
    const error = ref<string | null>(null);
    const lastQuery = ref('');
    const showModal = ref(false);
    const showLimitModal = ref(false);

    const enabled = computed(() => status.value?.enabled ?? false);
    const subscribed = computed(() => status.value?.subscribed ?? false);
    const unlimited = computed(() => status.value?.unlimited ?? false);
    const isAdmin = computed(() => status.value?.is_admin ?? false);
    const tokenBalance = computed(() => status.value?.token_balance ?? 0);
    const hasCoupon = computed(() => status.value?.has_coupon ?? false);
    const remainingSearches = computed(() => status.value?.searches.remaining ?? 0);
    const remainingViews = computed(() => status.value?.views.remaining ?? 0);

    const refreshStatus = async () => {
        try {
            status.value = await request<ExternalSearchStatus>('/api/external-search/status');
        } catch {
            status.value = null;
        }
    };

    const loadCached = async (limit = 24) => {
        loadingCached.value = true;
        try {
            const payload = await request<{ results: ExternalCar[] }>(
                `/api/external-search/cached?limit=${limit}`,
            );
            cachedCars.value = payload.results ?? [];
        } catch {
            cachedCars.value = [];
        } finally {
            loadingCached.value = false;
        }
    };

    const search = async (
        query: string,
        refresh = false,
        options: { modal?: boolean; criteria?: ExternalSearchCriteria } = {},
    ) => {
        const trimmed = query.trim();
        const criteria = options.criteria ?? {};
        const hasBrand = Boolean(criteria.brand_id);

        if ((!trimmed && !hasBrand) || loading.value) return;

        lastQuery.value = trimmed;
        loading.value = true;
        error.value = null;
        results.value = [];
        platformsSearched.value = [];
        showModal.value = options.modal ?? true;

        const body: Record<string, unknown> = {
            ...(trimmed ? { q: trimmed } : {}),
            ...(criteria.brand_id ? { brand_id: criteria.brand_id } : {}),
            ...(criteria.model_id ? { model_id: criteria.model_id } : {}),
            ...(criteria.year ? { year: Number(criteria.year) } : {}),
            ...(criteria.version_id ? { version_id: criteria.version_id } : {}),
            ...(criteria.city ? { city: criteria.city } : {}),
            ...(refresh ? { refresh: true } : {}),
        };

        try {
            const payload = await request<ExternalSearchResponse>('/api/external-search', {
                method: 'POST',
                body: JSON.stringify(body),
            });

            if (payload.limitReached) {
                showModal.value = false;
                showLimitModal.value = true;
                await refreshStatus();
                return;
            }

            results.value = payload.results ?? [];
            platformsSearched.value = payload.platformsSearched ?? [];
            error.value = payload.error ?? null;
            await refreshStatus();
        } catch (cause) {
            error.value = cause instanceof Error ? cause.message : 'Error al buscar.';
        } finally {
            loading.value = false;
        }
    };

    const openExternal = (car: ExternalCar) => {
        if (!car.id) return;

        showModal.value = false;
        router.visit(car.url || `/auto-externo/${encodeURIComponent(car.slug || car.id)}`);
    };

    const openOriginal = async (car: ExternalCar) => {
        if (!car.id) return;

        try {
            const response = await request<ExternalResolveResponse>('/api/external-search/resolve', {
                method: 'POST',
                body: JSON.stringify({ id: car.id }),
            });

            if (response.dead) {
                error.value = 'El anuncio ya no está disponible.';
                return;
            }

            if (response.allowed && response.sourceUrl) {
                await refreshStatus();
                window.open(response.sourceUrl, '_blank', 'noopener,noreferrer');
                return;
            }

            showLimitModal.value = true;
            await refreshStatus();
        } catch (cause) {
            error.value = cause instanceof Error ? cause.message : 'No se pudo abrir el anuncio.';
        }
    };

    const openModal = () => {
        showModal.value = true;
    };

    const closeModal = () => {
        showModal.value = false;
    };

    const openLimitModal = () => {
        showLimitModal.value = true;
    };

    const closeLimitModal = () => {
        showLimitModal.value = false;
    };

    const startReward = () =>
        request<{ viewId: string }>('/api/external-search/reward/start', { method: 'POST' });

    const claimReward = (viewId: string) =>
        request<{ balance: number }>('/api/external-search/reward/claim', {
            method: 'POST',
            body: JSON.stringify({ view_id: viewId }),
        }).then(async (payload) => {
            await refreshStatus();
            return payload;
        });

    return {
        status,
        results,
        platformsSearched,
        cachedCars,
        loading,
        loadingCached,
        error,
        lastQuery,
        showModal,
        showLimitModal,
        enabled,
        subscribed,
        unlimited,
        isAdmin,
        tokenBalance,
        hasCoupon,
        remainingSearches,
        remainingViews,
        refreshStatus,
        loadCached,
        search,
        openExternal,
        openOriginal,
        openModal,
        closeModal,
        openLimitModal,
        closeLimitModal,
        startReward,
        claimReward,
    };
}
