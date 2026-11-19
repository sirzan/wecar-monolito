import { computed, nextTick, ref } from 'vue';

type FormLike = {
    errors: Record<string, string>;
    data?: () => Record<string, unknown>;
};

export function useRequiredValidation(form: FormLike, labels: Record<string, string> | (() => Record<string, string>)) {
    const resolveLabels = (): Record<string, string> => (typeof labels === 'function' ? labels() : labels);
    const clientErrors = ref<Record<string, string>>({});

    const errors = computed<Record<string, string>>(() => ({
        ...clientErrors.value,
        ...(form.errors ?? {}),
    }));

    const fieldError = (field: string): string => errors.value[field] ?? '';
    const hasError = (field: string): boolean => Boolean(errors.value[field]);
    const invalidClass = (field: string): string => (hasError(field) ? 'border-destructive ring-2 ring-destructive/20' : '');

    const summary = computed(() => Object.keys(errors.value).map((field) => resolveLabels()[field] ?? field));

    const focusFirst = () => {
        const first = Object.keys(errors.value)[0];

        if (!first) return;

        void nextTick(() => {
            const element = document.querySelector<HTMLElement>(`[data-field="${first}"]`)
                ?? document.getElementById(first)
                ?? document.querySelector<HTMLElement>(`[name="${first}"]`);

            element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            element?.focus({ preventScroll: true });
        });
    };

    const validate = (): boolean => {
        const data = form.data ? form.data() : {};
        const next: Record<string, string> = {};
        const fields = resolveLabels();

        for (const field of Object.keys(fields)) {
            if (!(field in data)) continue;

            const value = data[field];

            if (value === null || value === undefined || String(value).trim() === '' || value === false) {
                next[field] = 'Este campo es obligatorio.';
            }
        }

        clientErrors.value = next;

        if (Object.keys(next).length) {
            focusFirst();
            return false;
        }

        return true;
    };

    const clearField = (field: string) => {
        if (clientErrors.value[field]) {
            const next = { ...clientErrors.value };
            delete next[field];
            clientErrors.value = next;
        }
    };

    const clear = () => {
        clientErrors.value = {};
    };

    return { errors, fieldError, hasError, invalidClass, summary, validate, clearField, clear, focusFirst };
}
