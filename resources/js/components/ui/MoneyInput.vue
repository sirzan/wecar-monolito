<script setup lang="ts">
import { ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id?: string;
        placeholder?: string;
        class?: string;
        disabled?: boolean;
        allowDecimals?: boolean;
        min?: number;
        invalid?: boolean;
    }>(),
    {
        placeholder: '0',
        allowDecimals: false,
    },
);

const model = defineModel<string | number | null>({ default: null });
const display = ref('');
let updating = false;

const format = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined || value === '') return '';

    const raw = String(value).replace(/,/g, '').trim();
    const [integer, ...decimalParts] = raw.split('.');
    const normalizedInteger = integer.replace(/[^\d-]/g, '') || '0';
    const grouped = Number(normalizedInteger).toLocaleString('es-MX');

    if (props.allowDecimals && decimalParts.length) {
        return `${grouped}.${decimalParts.join('').replace(/[^\d]/g, '').slice(0, 2)}`;
    }

    return grouped;
};

const syncDisplay = () => {
    updating = true;
    display.value = format(model.value);
    updating = false;
};

watch(() => model.value, syncDisplay, { immediate: true });

const onInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const raw = input.value.replace(/,/g, '');

    if (raw === '' || raw === '-') {
        display.value = raw;
        model.value = null;
        return;
    }

    const sanitized = props.allowDecimals
        ? raw.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1')
        : raw.replace(/[^\d]/g, '');

    if (sanitized === '') {
        display.value = '';
        model.value = null;
        return;
    }

    const amount = Number(sanitized);

    if (Number.isFinite(amount)) {
        model.value = amount;
    }

    display.value = format(sanitized);
};

const onBlur = () => {
    updateDisplayFromModel();
};

const updateDisplayFromModel = () => {
    if (updating) return;
    display.value = format(model.value);
};
</script>

<template>
    <input
        :id="id"
        :value="display"
        type="text"
        inputmode="decimal"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                invalid ? 'border-destructive ring-2 ring-destructive/20 focus-visible:ring-destructive' : '',
                props.class,
            )
        "
        @input="onInput"
        @blur="onBlur"
    />
</template>
