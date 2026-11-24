<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Globe, SearchX, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Label from '@/components/ui/Label.vue';
import ExternalCarCard from '@/components/external/ExternalCarCard.vue';
import ExternalSearchAnimation from '@/components/external/ExternalSearchAnimation.vue';
import type {
    ExternalCar,
    ExternalPlatform,
    ExternalSearchCriteria,
    ExternalSearchStatus,
} from '@/types/external';

const props = defineProps<{
    open: boolean;
    loading: boolean;
    results: ExternalCar[];
    error: string | null;
    status: ExternalSearchStatus | null;
    brands: { id: string; name: string; slug: string }[];
    models: { id: string; name: string; brand_id: string }[];
    versions: { id: string; name: string; model_id: string }[];
    cities: { value: string; count: number }[];
    initialCriteria?: Partial<ExternalSearchCriteria> | null;
    yearMin?: number;
    yearMax?: number;
}>();

const emit = defineEmits<{
    close: [];
    search: [payload: { criteria: ExternalSearchCriteria; query: string }];
    openCar: [car: ExternalCar];
    retry: [];
}>();

const form = reactive({
    brand_id: '',
    model_id: '',
    version_id: '',
    year: new Date().getFullYear() - 2,
    city: '',
});

const platforms = computed<ExternalPlatform[]>(() => props.status?.platforms ?? []);

const yearMin = computed(() => props.yearMin ?? 1990);
const yearMax = computed(() => props.yearMax ?? new Date().getFullYear() + 1);

const modelOptions = computed(() =>
    form.brand_id ? props.models.filter((model) => model.brand_id === form.brand_id) : [],
);

const versionOptions = computed(() =>
    form.model_id ? props.versions.filter((version) => version.model_id === form.model_id) : [],
);

const selectedBrand = computed(() => props.brands.find((brand) => brand.id === form.brand_id) ?? null);
const selectedModel = computed(() => modelOptions.value.find((model) => model.id === form.model_id) ?? null);
const selectedVersion = computed(() => versionOptions.value.find((version) => version.id === form.version_id) ?? null);

const ready = computed(() => form.brand_id !== '' && form.model_id !== '' && Number(form.year) > 0);

const applyCriteria = () => {
    const criteria = props.initialCriteria;

    if (!criteria) return;

    form.brand_id = criteria.brand_id ?? '';
    form.model_id = criteria.model_id ?? '';
    form.version_id = criteria.version_id ?? '';
    form.year = Number(criteria.year ?? form.year);
    form.city = criteria.city ?? '';

    if (form.brand_id && !props.brands.some((brand) => brand.id === form.brand_id)) {
        form.brand_id = '';
        form.model_id = '';
    }

    if (form.model_id && !modelOptions.value.some((model) => model.id === form.model_id)) {
        form.model_id = '';
    }
};

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        if (props.initialCriteria) {
            applyCriteria();
        }
    },
);

watch(
    () => form.brand_id,
    () => {
        if (form.model_id && !modelOptions.value.some((model) => model.id === form.model_id)) {
            form.model_id = '';
        }
        form.version_id = '';
    },
);

watch(
    () => form.model_id,
    () => {
        if (form.version_id && !versionOptions.value.some((version) => version.id === form.version_id)) {
            form.version_id = '';
        }
    },
);

const clear = () => {
    form.brand_id = '';
    form.model_id = '';
    form.version_id = '';
    form.year = new Date().getFullYear() - 2;
    form.city = '';
};

const submit = () => {
    if (!ready.value) return;

    const criteria: ExternalSearchCriteria = {
        brand_id: form.brand_id,
        model_id: form.model_id,
        year: Number(form.year),
        ...(form.version_id ? { version_id: form.version_id } : {}),
        ...(form.city ? { city: form.city } : {}),
    };

    const query = [
        selectedBrand.value?.name,
        selectedModel.value?.name,
        String(form.year),
        selectedVersion.value?.name ?? '',
        form.city,
    ]
        .filter(Boolean)
        .join(' ');

    emit('search', { criteria, query });
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[90] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="emit('close')">
            <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl bg-card shadow-elegant">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary"><Globe class="h-5 w-5" /></span>
                        <div>
                            <h2 class="text-lg font-black text-secondary">Buscar en otras plataformas</h2>
                            <p class="text-xs text-muted-foreground">Elige marca, modelo y año exacto para buscar en Mercado Libre, Seminuevos, Kavak y más.</p>
                        </div>
                    </div>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-muted" aria-label="Cerrar" @click="emit('close')"><X class="h-5 w-5" /></button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <Label for="ext-brand">Marca *</Label>
                            <select id="ext-brand" v-model="form.brand_id" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option value="">Selecciona una marca</option>
                                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label for="ext-model">Modelo *</Label>
                            <select id="ext-model" v-model="form.model_id" :disabled="!form.brand_id" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm disabled:opacity-50">
                                <option value="">{{ form.brand_id ? 'Selecciona el modelo' : 'Elige una marca primero' }}</option>
                                <option v-for="model in modelOptions" :key="model.id" :value="model.id">{{ model.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label for="ext-version">Versión (opcional)</Label>
                            <select id="ext-version" v-model="form.version_id" :disabled="!form.model_id || versionOptions.length === 0" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm disabled:opacity-50">
                                <option value="">{{ form.model_id ? 'Cualquier versión' : 'Elige un modelo primero' }}</option>
                                <option v-for="version in versionOptions" :key="version.id" :value="version.id">{{ version.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label for="ext-year">Año *</Label>
                            <select id="ext-year" v-model.number="form.year" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option v-for="year in Array.from({ length: yearMax - yearMin + 1 }, (_, index) => yearMax - index)" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div>
                            <Label for="ext-city">Ciudad (opcional)</Label>
                            <select id="ext-city" v-model="form.city" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option value="">Cualquier ciudad</option>
                                <option v-for="city in cities" :key="city.value" :value="city.value">{{ city.value }}</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <Button variant="hero" class="h-11 w-full rounded-full" :disabled="!ready || loading" @click="submit">
                                Buscar ahora
                            </Button>
                            <Button variant="outline" class="h-11 rounded-full" @click="clear">Limpiar</Button>
                        </div>
                    </div>

                    <p v-if="!ready" class="mt-3 text-xs font-semibold text-amber-600">
                        Selecciona marca, modelo y año exacto para iniciar la búsqueda externa.
                    </p>

                    <div class="mt-6 border-t border-border pt-5">
                        <div v-if="loading">
                            <ExternalSearchAnimation :platforms="platforms" class="py-6 sm:py-8" />
                        </div>

                        <div v-else-if="error && results.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
                            <SearchX class="h-9 w-9 text-destructive" />
                            <p class="mt-3 text-sm font-bold text-secondary">No se pudieron obtener resultados</p>
                            <p class="mt-1 max-w-md text-xs text-muted-foreground">{{ error }}</p>
                            <Button variant="outline" class="mt-4 rounded-full" @click="emit('retry')">Reintentar</Button>
                        </div>

                        <div v-else-if="results.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
                            <Globe class="h-9 w-9 text-muted-foreground" />
                            <p class="mt-3 text-sm font-bold text-secondary">Aún no hay resultados</p>
                            <p class="mt-1 max-w-md text-xs text-muted-foreground">Completa los datos y pulsa “Buscar ahora”.</p>
                        </div>

                        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <ExternalCarCard v-for="(car, index) in results" :key="car.id" :car="car" :index="index" @open="emit('openCar', $event)" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border px-5 py-3">
                    <p class="text-[11px] text-muted-foreground">
                        {{ results.length ? `${results.length} autos externos` : 'Los anuncios provienen de fuentes públicas' }}
                    </p>
                    <Button variant="outline" size="sm" @click="emit('close')">Cerrar</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
