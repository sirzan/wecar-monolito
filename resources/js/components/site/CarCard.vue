<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    GitCompare,
    Heart,
    Landmark,
    MapPin,
    Repeat,
    Share2,
    ShieldCheck,
    Tag,
    X,
} from '@lucide/vue';
import CarPhotoStrip from '@/components/site/CarPhotoStrip.vue';
import { useCompareStore } from '@/stores/compare';
import { cn } from '@/lib/utils';

export interface CarCardData {
    id: string;
    slug: string;
    title: string;
    brand?: string | null;
    model?: string | null;
    version?: string | null;
    year: number;
    km: number;
    price: number;
    previous_price?: number | null;
    city?: string | null;
    image?: string | null;
    gallery?: string[] | null;
    transmission?: string | null;
    fuel?: string | null;
    body?: string | null;
    featured?: boolean;
    verified?: boolean;
    accepts_trade?: boolean;
    status?: string | null;
    status_label?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    url: string;
}

const props = defineProps<{ car: CarCardData }>();
const compare = useCompareStore();

const favorite = ref(false);
const lightbox = ref(false);
const lightboxIndex = ref(0);

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const approxMonthly = (price: number) => Math.round((price * 0.8 * 0.0268) / 100) * 100;

const slides = computed(() => {
    const all = [props.car.image, ...(props.car.gallery ?? [])].filter(Boolean) as string[];
    return all.length ? Array.from(new Set(all)) : ['/placeholder.svg'];
});

const compared = computed(() => compare.has(props.car.id));
const isDiscounted = computed(
    () => Boolean(props.car.previous_price && props.car.price < props.car.previous_price),
);

const statusBadge = computed(() => {
    if (!props.car.status_label || props.car.status_label === 'En Venta') return null;
    const map: Record<string, { label: string; className: string }> = {
        Pendiente: { label: 'PENDIENTE', className: 'bg-amber-500' },
        Cancelado: { label: 'CANCELADO', className: 'bg-gray-500' },
        Descuento: { label: 'DESCUENTO', className: 'bg-purple-500' },
        Vendido: { label: 'VENDIDO', className: 'bg-rose-500' },
        Separado: { label: 'SEPARADO', className: 'bg-[#0f1729]' },
        Pausado: { label: 'PAUSADO', className: 'bg-slate-500' },
    };
    return map[props.car.status_label] ?? { label: props.car.status_label.toUpperCase(), className: 'bg-blue-500' };
});

const openLightbox = (index: number) => {
    lightboxIndex.value = index;
    lightbox.value = true;
};

const moveLightbox = (direction: number) => {
    const total = slides.value.length;
    lightboxIndex.value = (lightboxIndex.value + direction + total) % total;
};

const share = async () => {
    const url = `${window.location.origin}${props.car.url}`;
    const title = `${props.car.brand ?? ''} ${props.car.model ?? ''} ${props.car.year}`.trim();

    if (navigator.share) {
        try {
            await navigator.share({ title, url });
            return;
        } catch {
            // Usuario canceló.
        }
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // Sin permisos de portapapeles.
    }
};

const whatsappUrl = computed(() => {
    const text = `Hola, me interesa el ${props.car.brand ?? ''} ${props.car.model ?? ''} ${props.car.year}`;
    return `https://wa.me/5218112345678?text=${encodeURIComponent(text)}`;
});
</script>

<template>
    <div class="group relative flex h-full flex-row overflow-hidden rounded-3xl border border-border bg-card shadow-soft transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/40 hover:shadow-elegant sm:flex-col">
        <CarPhotoStrip
            :slides="slides"
            :alt="`${car.brand ?? ''} ${car.model ?? ''} ${car.year}`"
            class="aspect-square w-[36%] shrink-0 sm:aspect-[4/3] sm:w-full"
            @open="openLightbox"
        >
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-secondary/30 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
            <span
                v-if="statusBadge"
                class="pointer-events-none absolute right-0 top-3 rounded-l-full px-2.5 py-0.5 text-[9px] font-black tracking-widest text-white sm:top-4 sm:px-4 sm:py-1 sm:text-[10px]"
                :class="statusBadge.className"
            >
                {{ statusBadge.label }}
            </span>
            <div
                v-if="car.status_label === 'Pausado'"
                class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-secondary/60 backdrop-blur-[2px]"
            >
                <span class="rounded-full bg-slate-800/90 px-4 py-1.5 text-xs font-black tracking-widest text-white sm:px-6 sm:py-2 sm:text-sm">
                    PAUSADO
                </span>
            </div>
        </CarPhotoStrip>

        <div class="flex min-w-0 flex-1 flex-col gap-1 p-2.5 sm:gap-2 sm:p-5">
            <div class="flex items-start justify-between gap-2">
                <Link :href="car.url" class="min-w-0">
                    <h3 class="text-xs font-extrabold text-secondary sm:text-sm">
                        {{ car.brand }}
                        <span class="font-semibold text-muted-foreground">
                            • {{ car.model }}<template v-if="car.version"> {{ car.version }}</template>
                        </span>
                    </h3>
                </Link>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        aria-label="Favorito"
                        class="grid h-7 w-7 place-items-center rounded-full bg-accent/60 text-muted-foreground transition-all hover:scale-110 hover:text-primary sm:h-8 sm:w-8"
                        :class="favorite ? 'text-primary' : ''"
                        @click="favorite = !favorite"
                    >
                        <Heart class="h-3.5 w-3.5 sm:h-4 sm:w-4" :class="favorite ? 'fill-current' : ''" />
                    </button>
                    <button
                        type="button"
                        aria-label="Compartir"
                        class="grid h-7 w-7 place-items-center rounded-full bg-accent/60 text-muted-foreground transition-all hover:scale-110 sm:h-8 sm:w-8"
                        @click="share"
                    >
                        <Share2 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-muted-foreground sm:text-sm">{{ car.year }} • {{ car.km.toLocaleString('es-MX') }} km</p>

            <div class="mt-1 sm:mt-3">
                <p class="text-[10px] text-muted-foreground sm:text-[11px]">Precio</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="car.previous_price" class="text-sm font-bold text-muted-foreground line-through sm:text-xl">
                        {{ formatPrice(car.previous_price) }}
                    </span>
                    <p class="text-sm font-black text-primary sm:text-xl">{{ formatPrice(car.price) }}</p>
                </div>
                <p class="mt-0.5 text-[9px] font-semibold text-muted-foreground sm:text-[11px]">
                    Crédito desde <span class="font-bold text-secondary">{{ formatPrice(approxMonthly(car.price)) }}</span>/mes
                </p>
                <span v-if="car.city" class="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground sm:text-xs">
                    <MapPin class="h-3 w-3 sm:h-3.5 sm:w-3.5" /> {{ car.city }}
                </span>
            </div>

            <div v-if="isDiscounted || car.verified || car.accepts_trade" class="mt-1.5 flex flex-wrap gap-2">
                <span v-if="isDiscounted" class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-600 sm:px-2.5 sm:text-[11px]">
                    <Tag class="h-3 w-3" /> En Descuento
                </span>
                <span v-if="car.verified" class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 sm:px-2.5 sm:text-[11px]">
                    <ShieldCheck class="h-3 w-3" /> Verificado
                </span>
                <span v-if="car.accepts_trade" class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600 sm:px-2.5 sm:text-[11px]">
                    <Repeat class="h-3 w-3" /> Acepta cambios
                </span>
            </div>

            <div class="mt-auto flex w-full items-center gap-2 pt-1.5 sm:pt-3">
                <button
                    type="button"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[10px] font-bold transition-all sm:gap-2 sm:px-4 sm:py-2 sm:text-xs"
                    :class="
                        cn(
                            compared
                                ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                                : 'border-primary/40 text-primary hover:bg-primary hover:text-primary-foreground',
                        )
                    "
                    @click="compare.toggle({ id: car.id, title: car.title, image: car.image, brand: car.brand, model: car.model })"
                >
                    <GitCompare class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    {{ compared ? 'Quitar' : 'Comparar auto' }}
                </button>
                <Link
                    :href="`/credito?precio=${car.price}`"
                    title="Financiar auto"
                    aria-label="Financiar auto"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#0f1729] text-white shadow-soft transition-transform hover:scale-110 hover:bg-[#0f1729]/90 sm:h-10 sm:w-10"
                >
                    <Landmark class="h-4 w-4 sm:h-5 sm:w-5" />
                </Link>
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
    </div>

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
                    <img :src="slides[lightboxIndex]" :alt="car.title" class="max-h-[60vh] w-full object-contain" />
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
                        <p class="text-[10px] text-muted-foreground sm:text-xs">{{ formatPrice(car.price) }}</p>
                    </div>
                    <Link :href="car.url" class="shrink-0 rounded-full border border-primary/40 px-3 py-1.5 text-[10px] font-bold text-primary hover:bg-primary hover:text-primary-foreground sm:text-xs" @click="lightbox = false">
                        Ver ficha
                    </Link>
                </div>
            </div>
        </div>
    </Teleport>
</template>
