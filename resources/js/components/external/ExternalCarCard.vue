<script setup lang="ts">
import { computed, ref } from 'vue';
import { Car as CarIcon, Eye, GitCompare, Landmark, MapPin } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import { useCompareStore } from '@/stores/compare';
import { formatMxn } from '@/lib/currency';
import type { ExternalCar } from '@/types/external';

const props = defineProps<{ car: ExternalCar; index?: number }>();
const emit = defineEmits<{ open: [car: ExternalCar] }>();

const compare = useCompareStore();
const financeOpen = ref(false);
const imageFailed = ref(false);

const primaryImage = computed(() => (!imageFailed.value ? props.car.imageUrl : null));
const compared = computed(() => compare.has(props.car.id));
const financeCar = computed(() => ({
    id: props.car.id,
    title: props.car.title,
    brand: props.car.title.split(' ')[0] ?? null,
    model: props.car.title.split(' ').slice(1, 3).join(' ') || null,
    year: props.car.year,
    price: props.car.price,
    km: props.car.km,
}));

const formatKm = (value: number | null) =>
    value == null ? 'Kilometraje no especificado' : `${value.toLocaleString('es-MX')} km`;

const toggleCompare = () => {
    compare.toggle({
        id: props.car.id,
        title: props.car.title,
        image: props.car.imageUrl,
        brand: props.car.title.split(' ')[0] ?? null,
        model: props.car.title.split(' ').slice(1, 3).join(' ') || null,
    });
};
</script>

<template>
    <div class="group relative flex h-full flex-row overflow-hidden rounded-3xl border border-border bg-card shadow-soft transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/40 hover:shadow-elegant sm:flex-col">
        <button type="button" class="relative aspect-square w-[36%] shrink-0 overflow-hidden bg-muted text-left sm:aspect-[4/3] sm:w-full" @click="emit('open', car)">
            <img
                v-if="primaryImage"
                :src="primaryImage"
                :alt="car.title"
                loading="lazy"
                decoding="async"
                referrerpolicy="no-referrer"
                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                @error="imageFailed = true"
            />
            <span v-else class="grid h-full w-full place-items-center text-muted-foreground">
                <CarIcon class="h-9 w-9" />
            </span>

            <span
                class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-black text-white shadow-soft"
                :style="{ backgroundColor: car.platformColor || '#0f1729' }"
            >
                {{ car.platformInitials || 'EXT' }}
            </span>
            <span class="absolute right-3 top-3 rounded-full bg-secondary/90 px-2.5 py-1 text-[10px] font-bold text-secondary-foreground">
                {{ car.platformName || 'Otra plataforma' }}
            </span>
        </button>

        <div class="flex min-w-0 flex-1 flex-col gap-1 p-2.5 sm:gap-2 sm:p-5">
            <button type="button" class="text-left" @click="emit('open', car)">
                <h3 class="line-clamp-2 text-xs font-extrabold leading-snug text-secondary transition-colors group-hover:text-primary sm:text-sm">
                    {{ car.title }}
                </h3>
            </button>

            <p class="text-[11px] text-muted-foreground sm:text-sm">
                {{ car.year ?? 'Año no especificado' }} · {{ formatKm(car.km) }}
            </p>

            <p v-if="car.location" class="flex items-center gap-1 text-[10px] text-muted-foreground sm:text-xs">
                <MapPin class="h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5" /> {{ car.location }}
            </p>

            <div class="mt-1 sm:mt-3">
                <p class="text-[10px] text-muted-foreground sm:text-[11px]">Precio estimado</p>
                <p class="text-sm font-black text-primary sm:text-xl">{{ car.price == null ? 'Consultar precio' : formatMxn(car.price) }}</p>
                <p class="mt-0.5 text-[9px] font-semibold text-muted-foreground sm:text-[11px]">Anuncio de {{ car.platformName || 'otra plataforma' }}</p>
            </div>

            <div class="mt-auto flex w-full items-center gap-2 pt-1.5 sm:pt-3">
                <Button variant="outline" size="sm" class="flex-1 gap-1.5 rounded-full px-2.5 text-[10px] sm:px-4 sm:text-xs" @click="toggleCompare">
                    <GitCompare class="h-3.5 w-3.5 sm:h-4 sm:w-4" /> {{ compared ? 'Quitar' : 'Comparar' }}
                </Button>
                <Button variant="secondary" size="icon" class="h-8 w-8 shrink-0 rounded-full sm:h-10 sm:w-10" title="Financiar" @click="financeOpen = true">
                    <Landmark class="h-4 w-4 sm:h-5 sm:w-5" />
                </Button>
                <Button variant="hero" size="icon" class="h-8 w-8 shrink-0 rounded-full sm:h-10 sm:w-10" title="Ver ficha del auto" @click="emit('open', car)">
                    <Eye class="h-4 w-4 sm:h-5 sm:w-5" />
                </Button>
            </div>
        </div>

        <FinanciarAutoDialog :open="financeOpen" :car="financeCar" @close="financeOpen = false" />
    </div>
</template>
