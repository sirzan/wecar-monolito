import { computed, ref } from 'vue';
import type {
    ExternalCar,
    ExternalResolveResponse,
    ExternalSearchResponse,
    ExternalSearchStatus,
} from '@/types/external';

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
    const cachedCars = ref<ExternalCar[]>([]);
    const loading = ref(false);
    const loadingCached = ref(false);
    const error = ref<string | null>(null);
    const lastQuery = ref('');
    const showModal = ref(false);
    const showLimitModal = ref(false);
    const externalAd = ref<{ car: ExternalCar; sourceUrl: string } | null>(null);

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

    const search = async (query: string) => {
        if (!query.trim() || loading.value) return;
        lastQuery.value = query.trim();
        loading.value = true;
        error.value = null;
        results.value = [];
        showModal.value = true;

        try {
            const payload = await request<ExternalSearchResponse>('/api/external-search', {
                method: 'POST',
                body: JSON.stringify({ q: query.trim() }),
            });

            if (payload.limitReached) {
                showModal.value = false;
                showLimitModal.value = true;
                await refreshStatus();
                return;
            }

            results.value = payload.results ?? [];
            error.value = payload.error ?? null;
            await refreshStatus();
        } catch (cause) {
            error.value = cause instanceof Error ? cause.message : 'Error al buscar.';
        } finally {
            loading.value = false;
        }
    };

    const openExternal = async (car: ExternalCar) => {
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
                externalAd.value = { car, sourceUrl: response.sourceUrl };
                await refreshStatus();
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

    const closeExternalAd = () => {
        externalAd.value = null;
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
        cachedCars,
        loading,
        loadingCached,
        error,
        lastQuery,
        showModal,
        showLimitModal,
        externalAd,
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
        openModal,
        closeModal,
        openLimitModal,
        closeLimitModal,
        closeExternalAd,
        startReward,
        claimReward,
    };
}
