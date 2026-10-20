<script setup lang="ts">
import { computed, ref } from 'vue';
import { ExternalLink, GitCompare, Landmark, MapPin, Sparkles, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import { useCompareStore } from '@/stores/compare';
import type { ExternalCar } from '@/types/external';

const props = defineProps<{ car: ExternalCar; index?: number }>();
const emit = defineEmits<{ open: [car: ExternalCar] }>();

const compare = useCompareStore();
const detailOpen = ref(false);
const financeOpen = ref(false);
const imageFailed = ref(false);

const photos = computed(() =>
    Array.from(new Set([props.car.imageUrl, ...(props.car.gallery ?? [])].filter(Boolean) as string[])),
);
const primaryImage = computed(() => (!imageFailed.value ? photos.value[0] ?? null : null));
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

const formatPrice = (value: number | null) =>
    value == null
        ? 'Consultar precio'
        : new Intl.NumberFormat('es-MX', {
              style: 'currency',
              currency: 'MXN',
              maximumFractionDigits: 0,
          }).format(value);

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
    <div class="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-soft transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/40 hover:shadow-elegant">
        <button type="button" class="relative block aspect-[4/3] w-full overflow-hidden bg-muted text-left" @click="detailOpen = true">
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
            <span v-else class="grid h-full w-full place-items-center text-sm font-semibold text-muted-foreground">Sin foto</span>

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

        <div class="flex flex-1 flex-col p-4">
            <button type="button" class="text-left" @click="detailOpen = true">
                <h3 class="line-clamp-2 text-sm font-extrabold leading-snug text-secondary transition-colors group-hover:text-primary">
                    {{ car.title }}
                </h3>
            </button>

            <p class="mt-1 text-xs text-muted-foreground">
                {{ car.year ?? 'Año no especificado' }} · {{ formatKm(car.km) }}
            </p>

            <div class="mt-3">
                <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Precio estimado</p>
                <p class="text-xl font-black text-primary">{{ formatPrice(car.price) }}</p>
                <p v-if="car.location" class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                    <MapPin class="h-3 w-3" /> {{ car.location }}
                </p>
            </div>

            <div class="mt-auto flex items-center gap-2 pt-4">
                <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 gap-1.5 rounded-full text-[11px]"
                    @click="toggleCompare"
                >
                    <GitCompare class="h-3.5 w-3.5" /> {{ compared ? 'Quitar' : 'Comparar' }}
                </Button>
                <Button variant="secondary" size="icon" class="h-9 w-9 rounded-full" title="Financiar" @click="financeOpen = true">
                    <Landmark class="h-4 w-4" />
                </Button>
                <Button variant="hero" size="icon" class="h-9 w-9 rounded-full" title="Ver anuncio" @click="emit('open', car)">
                    <ExternalLink class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="detailOpen" class="fixed inset-0 z-[100] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="detailOpen = false">
                <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-card shadow-elegant">
                    <div class="relative">
                        <img v-if="primaryImage" :src="primaryImage" :alt="car.title" referrerpolicy="no-referrer" class="aspect-[16/9] w-full object-cover" />
                        <div v-else class="grid aspect-[16/9] place-items-center bg-muted text-muted-foreground">Sin foto</div>
                        <button type="button" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-secondary/80 text-white" @click="detailOpen = false">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="flex items-center gap-1 text-xs font-bold text-primary">
                                    <Sparkles class="h-3.5 w-3.5" /> Encontrado por búsqueda inteligente
                                </p>
                                <h2 class="mt-1 text-xl font-black text-secondary">{{ car.title }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ [car.year, car.km != null ? formatKm(car.km) : null, car.location].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold text-white" :style="{ backgroundColor: car.platformColor || '#0f1729' }">
                                {{ car.platformName || 'Otra plataforma' }}
                            </span>
                        </div>

                        <p class="text-3xl font-black text-secondary">{{ formatPrice(car.price) }}</p>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-muted/50 p-3">
                                <p class="text-[11px] text-muted-foreground">Año</p>
                                <p class="font-bold text-secondary">{{ car.year ?? 'No especificado' }}</p>
                            </div>
                            <div class="rounded-2xl bg-muted/50 p-3">
                                <p class="text-[11px] text-muted-foreground">Kilometraje</p>
                                <p class="font-bold text-secondary">{{ formatKm(car.km) }}</p>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Datos obtenidos del anuncio original. Confirma los detalles con el vendedor antes de cualquier pago.
                        </p>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Button variant="secondary" class="flex-1 gap-2" @click="financeOpen = true">
                                <Landmark class="h-4 w-4" /> Financiar este auto
                            </Button>
                            <Button variant="hero" class="flex-1 gap-2" @click="detailOpen = false; emit('open', car)">
                                <ExternalLink class="h-4 w-4" /> Ver anuncio
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <FinanciarAutoDialog :open="financeOpen" :car="financeCar" @close="financeOpen = false" />
    </div>
</template>
