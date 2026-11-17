<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

const cp = defineModel<string>('cp', { default: '' });
const colonia = defineModel<string>('colonia', { default: '' });
const alcaldia = defineModel<string>('alcaldia', { default: '' });
const state = defineModel<string>('state', { default: '' });

const places = ref<{ colonia: string; municipio: string; estado: string }[]>([]);
const loading = ref(false);
const error = ref('');
const manual = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

const coloniaOptions = computed(() => {
    const options = places.value.map((place) => place.colonia);

    if (colonia.value && !options.includes(colonia.value)) {
        return [colonia.value, ...options];
    }

    return options;
});

const lookup = async () => {
    const value = cp.value.replace(/\D/g, '').slice(0, 5);
    cp.value = value;

    if (value.length !== 5) {
        places.value = [];
        error.value = '';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(`/api/codigo-postal/${value}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            places.value = [];
            error.value = payload.error ?? 'No pudimos consultar el código postal. Captura tu colonia manualmente.';
            manual.value = true;
            return;
        }

        places.value = payload.places ?? [];

        if (!places.value.length) {
            error.value = 'No encontramos colonias para ese código postal. Captura tu colonia manualmente.';
            manual.value = true;
        }
    } catch {
        places.value = [];
        error.value = 'No pudimos consultar el código postal. Captura tu colonia manualmente.';
        manual.value = true;
    } finally {
        loading.value = false;
    }
};

watch(
    () => cp.value,
    () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(lookup, 450);
    },
);

const selectColonia = () => {
    const place = places.value.find((item) => item.colonia === colonia.value);

    if (!place) return;

    if (!alcaldia.value) alcaldia.value = place.municipio;
    if (!state.value) state.value = place.estado;
};

onMounted(() => {
    if (cp.value.replace(/\D/g, '').length === 5) {
        void lookup();
    }
});
</script>

<template>
    <div class="contents">
        <div>
            <Label for="cp">Código postal</Label>
            <Input id="cp" v-model="cp" maxlength="5" inputmode="numeric" placeholder="Ej. 64000" class="mt-1.5" />
            <p v-if="loading" class="mt-1 text-[11px] text-muted-foreground">Buscando colonias…</p>
            <p v-if="error" class="mt-1 text-[11px] text-destructive">{{ error }}</p>
        </div>

        <div>
            <Label for="colonia">Colonia</Label>
            <select
                v-if="!manual && coloniaOptions.length"
                id="colonia"
                v-model="colonia"
                class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                @change="selectColonia"
            >
                <option value="">Selecciona una colonia</option>
                <option v-for="option in coloniaOptions" :key="option" :value="option">{{ option }}</option>
            </select>
            <Input v-else id="colonia" v-model="colonia" class="mt-1.5" placeholder="Escribe tu colonia" />
            <button v-if="!manual && coloniaOptions.length" type="button" class="mt-1 text-[11px] font-semibold text-primary hover:underline" @click="manual = true">
                Mi colonia no aparece
            </button>
        </div>

        <div>
            <Label for="alcaldia">Alcaldía / Municipio</Label>
            <Input id="alcaldia" v-model="alcaldia" class="mt-1.5" />
        </div>

        <div>
            <Label for="address-state">Estado</Label>
            <Input id="address-state" v-model="state" class="mt-1.5" />
        </div>
    </div>
</template>
