<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Heart, HeartOff, ImageOff } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import CarCard, { type CarCardData } from '@/components/site/CarCard.vue';
import ExternalCarCard from '@/components/external/ExternalCarCard.vue';
import Button from '@/components/ui/Button.vue';
import type { ExternalCar } from '@/types/external';

defineOptions({ layout: PanelLayout });

type FavoriteItem = {
    type: 'car' | 'external';
    id: string;
    car: CarCardData | null;
    external: ExternalCar | null;
    title: string;
    image: string | null;
    available: boolean;
    url: string;
};

const props = defineProps<{ items: FavoriteItem[] }>();

const removeFavorite = async (item: FavoriteItem) => {
    const id = item.id;

    if (!id) return;

    const endpoint = item.type === 'external'
        ? `/api/favorites/external/${id}`
        : `/api/favorites/${id}`;

    await fetch(endpoint, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
    });

    router.reload({ only: ['items'] });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div>
            <h1 class="text-2xl font-black text-secondary sm:text-3xl">Favoritos</h1>
            <p class="mt-1 text-sm text-muted-foreground">Tus autos guardados, disponibles en todos tus dispositivos.</p>
        </div>

        <div v-if="props.items.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="item in props.items" :key="item.type + item.id" class="relative">
                <ExternalCarCard v-if="item.external" :car="item.external" @open="router.visit(item.external?.url || item.url)" />
                <CarCard v-else-if="item.car" :car="item.car" />
                <article v-else class="overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
                    <div class="grid aspect-[4/3] place-items-center bg-muted text-muted-foreground">
                        <img v-if="item.image" :src="item.image" :alt="item.title" class="h-full w-full object-cover opacity-60" />
                        <ImageOff v-else class="h-9 w-9" />
                    </div>
                    <div class="p-5">
                        <h2 class="font-bold text-secondary">{{ item.title }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Esta publicación ya no está disponible.</p>
                    </div>
                </article>

                <button
                    type="button"
                    class="absolute right-3 top-3 z-10 inline-flex items-center gap-1 rounded-full bg-card/95 px-2.5 py-1 text-[10px] font-bold text-destructive shadow-soft backdrop-blur transition hover:bg-destructive hover:text-white"
                    @click="removeFavorite(item)"
                >
                    <HeartOff class="h-3 w-3" /> Quitar
                </button>
            </div>
        </div>

        <div v-else class="mt-6 grid place-items-center rounded-3xl border border-dashed border-border bg-card py-20 text-center">
            <Heart class="h-10 w-10 text-muted-foreground" />
            <h2 class="mt-4 text-lg font-bold text-secondary">Aún no tienes favoritos</h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">Los autos que selecciones con el ícono ❤️ aparecerán aquí.</p>
            <Button as="a" href="/comprar" class="mt-5">Explorar autos</Button>
        </div>
    </div>
</template>
