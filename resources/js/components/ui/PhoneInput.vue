<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id?: string;
        placeholder?: string;
        class?: string;
        disabled?: boolean;
        autocomplete?: string;
        required?: boolean;
    }>(),
    {
        placeholder: '81 1234 5678',
    },
);

const model = defineModel<string>({ default: '' });

const countries = [
    { code: '52', label: 'México +52', digits: 10 },
    { code: '1', label: 'EE.UU. +1', digits: 10 },
    { code: '34', label: 'España +34', digits: 9 },
    { code: '57', label: 'Colombia +57', digits: 10 },
    { code: '54', label: 'Argentina +54', digits: 10 },
];

const splitValue = (value: string): { code: string; number: string } => {
    const digits = String(value ?? '').replace(/\D/g, '');

    if (!digits) return { code: '52', number: '' };

    const match = [...countries]
        .sort((a, b) => b.code.length - a.code.length)
        .find((country) => digits.startsWith(country.code));

    return match
        ? { code: match.code, number: digits.slice(match.code.length) }
        : { code: '52', number: digits };
};

const initial = splitValue(model.value);
const countryCode = ref(initial.code);
const number = ref(initial.number);

const selectedCountry = computed(() => countries.find((country) => country.code === countryCode.value) ?? countries[0]);
const isInvalid = computed(() => number.value.length > 0 && number.value.length !== selectedCountry.value.digits);

watch(
    () => model.value,
    (value) => {
        const next = splitValue(value);

        if (`${next.code}${next.number}` !== `${countryCode.value}${number.value}`) {
            countryCode.value = next.code;
            number.value = next.number;
        }
    },
);

const sync = () => {
    let clean = number.value.replace(/\D/g, '');

    if (clean.startsWith(countryCode.value)) {
        clean = clean.slice(countryCode.value.length);
    }

    number.value = clean;
    model.value = clean ? `${countryCode.value}${clean}` : '';
};

const onCountryChange = () => {
    sync();
};
</script>

<template>
    <div
        :class="
            cn(
                'flex h-10 w-full overflow-hidden rounded-md border border-input bg-background ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                props.class,
                isInvalid ? 'border-destructive' : '',
            )
        "
    >
        <select
            v-model="countryCode"
            class="h-full shrink-0 border-0 bg-muted/50 px-2 text-xs font-semibold text-secondary outline-none"
            :disabled="disabled"
            aria-label="Lada telefónica"
            @change="onCountryChange"
        >
            <option v-for="country in countries" :key="country.code" :value="country.code">{{ country.label }}</option>
        </select>
        <input
            :id="id"
            v-model="number"
            type="tel"
            inputmode="tel"
            :placeholder="placeholder"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :required="required"
            class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm outline-none"
            @input="sync"
        />
    </div>
</template>
