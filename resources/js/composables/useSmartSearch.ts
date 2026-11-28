const ALLOWED_FILTERS = [
    'q',
    'brand',
    'model',
    'city',
    'body',
    'transmission',
    'fuel',
    'min_price',
    'max_price',
    'min_year',
    'max_year',
] as const;

export type SmartFilters = Record<(typeof ALLOWED_FILTERS)[number] | string, string>;

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/**
 * Interpreta una búsqueda en lenguaje natural y devuelve filtros listos
 * para el catálogo interno.
 */
export async function resolveSmartFilters(query: string): Promise<SmartFilters> {
    const trimmed = query.trim();

    const response = await fetch('/api/ai-search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ q: trimmed }),
    });

    if (!response.ok) {
        throw new Error('No se pudo interpretar la búsqueda.');
    }

    const payload = await response.json();
    const filters = payload?.filters ?? {};
    const result: SmartFilters = {};

    for (const key of ALLOWED_FILTERS) {
        const value = filters[key];

        if (value === undefined || value === null || value === '') {
            continue;
        }

        result[key] = String(value);
    }

    if (Object.keys(result).length === 0) {
        result.q = trimmed;
    }

    return result;
}
