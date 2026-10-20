<script setup lang="ts">
import { ExternalLink, MapPin } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import type { ExternalCar } from '@/types/external';

defineProps<{
    open: boolean;
    car: ExternalCar | null;
    sourceUrl: string | null;
}>();

const emit = defineEmits<{ close: [] }>();

const formatPrice = (value: number | null) =>
    value == null
        ? 'Consultar precio'
        : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const formatKm = (value: number | null) => (value == null ? null : `${value.toLocaleString('es-MX')} km`);

const openSite = (sourceUrl: string | null) => {
    if (sourceUrl) window.open(sourceUrl, '_blank', 'noopener,noreferrer');
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open && car" class="fixed inset-0 z-[95] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="emit('close')">
            <div class="w-full max-w-lg overflow-hidden rounded-3xl bg-card shadow-elegant">
                <img v-if="car.imageUrl" :src="car.imageUrl" :alt="car.title" referrerpolicy="no-referrer" class="aspect-[4/3] w-full object-cover" />
                <div v-else class="grid aspect-[4/3] place-items-center bg-muted text-sm text-muted-foreground">Sin foto</div>

                <div class="p-5">
                    <p class="text-xs font-bold text-primary">{{ car.platformName || 'Anuncio externo' }}</p>
                    <h2 class="mt-1 text-lg font-black leading-snug text-secondary">{{ car.title }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <span class="text-xl font-black text-primary">{{ formatPrice(car.price) }}</span>
                        <span v-if="car.year" class="text-sm text-muted-foreground">{{ car.year }}</span>
                        <span v-if="formatKm(car.km)" class="text-sm text-muted-foreground">{{ formatKm(car.km) }}</span>
                    </div>
                    <p v-if="car.location" class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                        <MapPin class="h-3.5 w-3.5" /> {{ car.location }}
                    </p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <Button variant="hero" class="flex-1 gap-2" @click="openSite(sourceUrl)">
                            <ExternalLink class="h-4 w-4" /> Ver en el sitio original
                        </Button>
                        <Button variant="outline" class="flex-1" @click="emit('close')">Seguir en WeCar</Button>
                    </div>
                    <p class="mt-2 text-[11px] text-muted-foreground">El anuncio se abrirá en una pestaña nueva.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
