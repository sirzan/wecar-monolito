<script setup lang="ts">
import { Globe, LoaderCircle, SearchX, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import ExternalCarCard from '@/components/external/ExternalCarCard.vue';
import type { ExternalCar } from '@/types/external';

defineProps<{
    open: boolean;
    loading: boolean;
    results: ExternalCar[];
    error: string | null;
    query: string;
}>();

const emit = defineEmits<{ close: []; open: [car: ExternalCar]; retry: [] }>();
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[90] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="emit('close')">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl bg-card shadow-elegant">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary"><Globe class="h-5 w-5" /></span>
                        <div>
                            <h2 class="text-lg font-black text-secondary">Búsqueda en la red</h2>
                            <p class="text-xs text-muted-foreground">Autos encontrados para "{{ query }}"</p>
                        </div>
                    </div>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-muted" @click="emit('close')"><X class="h-5 w-5" /></button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-center">
                        <LoaderCircle class="h-10 w-10 animate-spin text-primary" />
                        <p class="mt-4 text-sm font-bold text-secondary">Buscando más opciones...</p>
                        <p class="mt-1 text-xs text-muted-foreground">Explorando Mercado Libre, Facebook, Seminuevos, Kavak y Autoscosmos.</p>
                    </div>

                    <div v-else-if="error && results.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <SearchX class="h-10 w-10 text-destructive" />
                        <p class="mt-4 text-sm font-bold text-secondary">No se pudieron obtener resultados</p>
                        <p class="mt-1 max-w-md text-xs text-muted-foreground">{{ error }}</p>
                        <Button variant="outline" class="mt-4" @click="emit('retry')">Reintentar</Button>
                    </div>

                    <div v-else-if="results.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <SearchX class="h-10 w-10 text-muted-foreground" />
                        <p class="mt-4 text-sm font-bold text-secondary">No encontramos resultados</p>
                        <p class="mt-1 text-xs text-muted-foreground">Intenta con otra búsqueda o revisa el catálogo local.</p>
                    </div>

                    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <ExternalCarCard v-for="(car, index) in results" :key="car.id" :car="car" :index="index" @open="emit('open', $event)" />
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
