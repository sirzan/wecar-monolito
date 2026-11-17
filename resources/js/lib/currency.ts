const mxn = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

const mxnDecimal = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatMxn(value: number | string | null | undefined): string {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '—';
    }

    return mxn.format(amount);
}

export function formatMxnDecimal(value: number | string | null | undefined): string {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '—';
    }

    return mxnDecimal.format(amount);
}

export function formatAmountInput(value: number | string | null | undefined): string {
    const digits = String(value ?? '').replace(/[^\d.]/g, '');

    if (digits === '') {
        return '';
    }

    const [integer, ...decimals] = digits.split('.');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return decimals.length ? `${grouped}.${decimals.join('')}` : grouped;
}

export function parseAmount(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const amount = Number(value.replace(/[^\d.-]/g, ''));

    return Number.isFinite(amount) ? amount : null;
}
