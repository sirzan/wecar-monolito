export interface CreditSimulatorDraft {
    year?: number | null;
    brand?: string;
    brand_id?: string;
    model?: string;
    model_id?: string;
    version?: string;
    version_id?: string;
    price?: number;
    downPaymentPct?: number;
    term?: number;
    km?: number;
    carId?: string;
}

export interface CreditDialogDraft {
    step?: 1 | 2 | 3 | 4 | 5;
    basics?: {
        downPayment: number;
        term: number;
        phone: string;
    };
    profile?: {
        income: number | null;
        tenure: number | null;
        debts: number;
        age: number | null;
        employment: string;
        bureau: string;
        brandId: string;
        modelId: string;
        brand: string;
        model: string;
        version: string;
        versionId: string;
        year: number | null;
        price: number;
    };
    application?: {
        full_name: string;
        email: string;
        phone: string;
        birthdate: string;
        rfc: string;
        curp: string;
        calle: string;
        colonia: string;
        cp: string;
        alcaldia: string;
        ciudad: string;
        state: string;
        pais: string;
        ocupacion: string;
        empresa: string;
        antiguedad: string;
        banco: string;
        tipo_cuenta: string;
    };
}

export interface CreditDraftPayload {
    savedAt: number;
    autoReopen?: boolean;
    simulator?: CreditSimulatorDraft;
    dialog?: CreditDialogDraft;
}

const STORAGE_PREFIX = 'wecar:credit-draft:';
const DRAFT_TTL_MS = 30 * 60 * 1000;

const storageKey = (scope: string): string => `${STORAGE_PREFIX}${scope}`;

export function loadCreditDraft(scope: string): CreditDraftPayload | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(storageKey(scope));

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as CreditDraftPayload;

        if (!parsed || typeof parsed.savedAt !== 'number' || Date.now() - parsed.savedAt > DRAFT_TTL_MS) {
            window.sessionStorage.removeItem(storageKey(scope));
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function saveCreditDraft(
    scope: string,
    patch: Omit<Partial<CreditDraftPayload>, 'savedAt'>,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const current = loadCreditDraft(scope);
        const next: CreditDraftPayload = {
            savedAt: Date.now(),
            autoReopen: patch.autoReopen ?? current?.autoReopen,
            simulator: patch.simulator
                ? { ...(current?.simulator ?? {}), ...patch.simulator }
                : current?.simulator,
            dialog: patch.dialog
                ? { ...(current?.dialog ?? {}), ...patch.dialog }
                : current?.dialog,
        };

        window.sessionStorage.setItem(storageKey(scope), JSON.stringify(next));
    } catch {
        // Ignorar si sessionStorage no está disponible (modo privado/cuota).
    }
}

export function clearCreditDraft(scope: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.removeItem(storageKey(scope));
    } catch {
        // Ignorar errores de almacenamiento.
    }
}
