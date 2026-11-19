<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { ChevronLeft, ChevronRight, GitCompare, Heart, Landmark, MapPin, Share2, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import CarPhotoStrip from '@/components/site/CarPhotoStrip.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import { useCompareStore } from '@/stores/compare';
import { useFavoritesStore } from '@/stores/favorites';
import { formatMxn } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { toast } from 'vue-sonner';
import type { ExternalCar } from '@/types/external';

const props = defineProps<{ car: ExternalCar; index?: number }>();
const emit = defineEmits<{ open: [car: ExternalCar] }>();

const compare = useCompareStore();
const favorites = useFavoritesStore();
const financeOpen = ref(false);
const lightbox = ref(false);
const lightboxIndex = ref(0);
const isFavorite = computed(() => favorites.hasExternal(props.car.id));

onMounted(() => void favorites.bootstrap());

const slides = computed(() => {
    const images = [props.car.imageUrl, ...(props.car.gallery ?? [])]
        .filter((image): image is string => Boolean(image));

    return images.length ? Array.from(new Set(images)) : ['/placeholder.svg'];
});

const compared = computed(() => compare.has(props.car.id));
const detailUrl = computed(() => props.car.url || `/auto-externo/${props.car.slug || props.car.id}`);
const approxMonthly = (price: number) => Math.round((price * 0.8 * 0.0268) / 100) * 100;

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

const openLightbox = (index: number) => {
    lightboxIndex.value = index;
    lightbox.value = true;
};

const moveLightbox = (direction: number) => {
    const total = slides.value.length;
    lightboxIndex.value = (lightboxIndex.value + direction + total) % total;
};

const share = async () => {
    const url = new URL(detailUrl.value, window.location.origin).toString();

    if (navigator.share) {
        try {
            await navigator.share({ title: props.car.title, url });
            return;
        } catch {
            // Usuario canceló.
        }
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace del anuncio copiado.');
    } catch {
        toast.error('No pudimos copiar el enlace.');
    }
};

const toggleCompare = () => {
    compare.toggle({
        id: props.car.id,
        title: props.car.title,
        image: props.car.imageUrl,
        brand: props.car.title.split(' ')[0] ?? null,
        model: props.car.title.split(' ').slice(1, 3).join(' ') || null,
    });
};

const whatsappUrl = computed(() => {
    const text = `Hola, vi este auto externo "${props.car.title}" publicado en ${props.car.platformName || 'otra plataforma'} y quiero más información. Anuncio: ${props.car.sourceUrl || detailUrl.value}`;
    return `https://wa.me/5218112345678?text=${encodeURIComponent(text)}`;
});
</script>

<template>
    <div class="group relative flex h-full flex-row overflow-hidden rounded-3xl border border-primary/30 bg-card shadow-soft transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/60 hover:shadow-elegant sm:flex-col">
        <CarPhotoStrip
            :slides="slides"
            :alt="car.title"
            class="aspect-square w-[36%] shrink-0 sm:aspect-[4/3] sm:w-full"
            @open="openLightbox"
        >
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-secondary/30 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
        </CarPhotoStrip>

        <div class="flex min-w-0 flex-1 flex-col gap-1 p-2.5 sm:gap-2 sm:p-5">
            <div class="flex items-start justify-between gap-2">
                <button type="button" class="min-w-0 text-left" @click="emit('open', car)">
                    <h3 class="line-clamp-2 text-xs font-extrabold leading-snug text-secondary transition-colors group-hover:text-primary sm:text-sm">
                        {{ car.title }}
                    </h3>
                </button>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        :aria-label="isFavorite ? 'Quitar de favoritos' : 'Agregar a favoritos'"
                        class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent/60 text-muted-foreground transition-all hover:scale-110 hover:text-primary sm:h-8 sm:w-8"
                        :class="isFavorite ? 'text-primary' : ''"
                        @click="favorites.toggleExternal(car.id)"
                    >
                        <Heart class="h-3.5 w-3.5 sm:h-4 sm:w-4" :class="isFavorite ? 'fill-current' : ''" />
                    </button>
                    <button
                        type="button"
                        aria-label="Compartir anuncio externo"
                        class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent/60 text-muted-foreground transition-all hover:scale-110 hover:text-primary sm:h-8 sm:w-8"
                        @click="share"
                    >
                        <Share2 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-muted-foreground sm:text-sm">
                {{ car.year ?? 'Año no especificado' }} · {{ formatKm(car.km) }}
            </p>

            <div class="mt-1 sm:mt-3">
                <p class="text-[10px] text-muted-foreground sm:text-[11px]">Precio estimado</p>
                <p class="text-sm font-black text-primary sm:text-xl">{{ car.price == null ? 'Consultar precio' : formatMxn(car.price) }}</p>
                <p v-if="car.price != null" class="mt-0.5 text-[9px] font-semibold text-muted-foreground sm:text-[11px]">
                    Crédito desde <span class="font-bold text-secondary">{{ formatMxn(approxMonthly(car.price)) }}</span>/mes
                </p>
                <p v-if="car.location" class="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground sm:text-xs">
                    <MapPin class="h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5" /> {{ car.location }}
                </p>
                <p class="mt-0.5 text-[9px] font-semibold text-muted-foreground sm:text-[11px]">Anuncio de {{ car.platformName || 'otra plataforma' }}</p>
            </div>

            <div class="mt-auto flex w-full items-center gap-2 pt-1.5 sm:pt-3">
                <button
                    type="button"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[10px] font-bold transition-all sm:gap-2 sm:px-4 sm:py-2 sm:text-xs"
                    :class="cn(
                        compared
                            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                            : 'border-primary/40 text-primary hover:bg-primary hover:text-primary-foreground',
                    )"
                    @click="toggleCompare"
                >
                    <GitCompare class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    {{ compared ? 'Quitar' : 'Comparar auto' }}
                </button>
                <Button variant="secondary" size="icon" class="h-8 w-8 shrink-0 rounded-full sm:h-10 sm:w-10" title="Financiar" @click="financeOpen = true">
                    <Landmark class="h-4 w-4 sm:h-5 sm:w-5" />
                </Button>
                <a
                    :href="whatsappUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Contactar por WhatsApp"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-500 text-white shadow-soft transition-transform hover:scale-110 hover:bg-emerald-600 sm:h-10 sm:w-10"
                >
                    <svg viewBox="0 0 32 32" class="h-4 w-4 sm:h-5 sm:w-5" fill="currentColor" aria-hidden="true">
                        <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 01-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 01-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.09-.832-2.335-.143-.372-.214-.487-.6-.487-.187 0-.36-.043-.53-.043-.302 0-.53.115-.746.315-.688.645-1.032 1.318-1.06 2.264v.114c-.015.99.472 1.977 1.017 2.78 1.23 1.82 2.506 3.41 4.554 4.34.616.287 2.035.888 2.722.888.817 0 2.15-.515 2.478-1.318.215-.515.215-.946.143-1.06-.058-.157-.244-.215-.515-.357-.272-.144-1.461-.717-1.677-.79zM16.001 28.685a12.39 12.39 0 01-6.15-1.62L5 28.366l1.32-4.778A12.366 12.366 0 013.685 16C3.685 9.21 9.213 3.685 16 3.685c3.296 0 6.394 1.282 8.72 3.611A12.31 12.31 0 0128.31 16c-.002 6.79-5.527 12.685-12.31 12.685zm0-22.95C10.34 5.735 5.735 10.34 5.735 16c0 1.94.546 3.842 1.578 5.49l.245.387-.785 2.86 2.954-.773.378.224a10.235 10.235 0 005.21 1.428c5.66 0 10.265-4.605 10.265-10.265a10.197 10.197 0 00-2.998-7.265 10.196 10.196 0 00-7.27-3.013z" />
                    </svg>
                </a>
            </div>
        </div>

        <FinanciarAutoDialog :open="financeOpen" :car="financeCar" @close="financeOpen = false" />

        <Teleport to="body">
            <div
                v-if="lightbox"
                role="dialog"
                aria-modal="true"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                @click="lightbox = false"
            >
                <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-card shadow-elegant" @click.stop>
                    <div class="relative flex items-center justify-center bg-secondary">
                        <img :src="slides[lightboxIndex]" :alt="car.title" class="max-h-[60vh] w-full object-contain" referrerpolicy="no-referrer" />
                        <button
                            v-if="slides.length > 1"
                            type="button"
                            aria-label="Foto anterior"
                            class="absolute left-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60"
                            @click="moveLightbox(-1)"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            v-if="slides.length > 1"
                            type="button"
                            aria-label="Foto siguiente"
                            class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60"
                            @click="moveLightbox(1)"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>
                        <span class="absolute left-3 top-3 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-bold text-white">
                            {{ lightboxIndex + 1 }} / {{ slides.length }}
                        </span>
                        <button
                            type="button"
                            aria-label="Cerrar"
                            class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-black/50 text-white hover:bg-black/70"
                            @click="lightbox = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-2 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-extrabold text-secondary sm:text-sm">{{ car.title }}</p>
                            <p class="text-[10px] text-muted-foreground sm:text-xs">{{ car.price == null ? 'Consultar precio' : formatMxn(car.price) }}</p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-full border border-primary/40 px-3 py-1.5 text-[10px] font-bold text-primary hover:bg-primary hover:text-primary-foreground sm:text-xs"
                            @click="lightbox = false; emit('open', car)"
                        >
                            Ver ficha
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
