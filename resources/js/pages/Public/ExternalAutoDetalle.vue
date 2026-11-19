<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { ExternalLink, MapPin, Sparkles } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import ExternalSearchLimitModal from '@/components/external/ExternalSearchLimitModal.vue';
import SearchRewardAdDialog from '@/components/external/SearchRewardAdDialog.vue';
import { formatMxn } from '@/lib/currency';
import { useExternalSearch } from '@/composables/useExternalSearch';
import type { ExternalCar } from '@/types/external';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    car: ExternalCar;
    seo: { title: string; description: string; canonical: string; noindex: boolean };
}>();

const externalSearch = useExternalSearch();
const rewardOpen = ref(false);

onMounted(() => {
    void externalSearch.refreshStatus();
});

const formatPrice = (value: number | null) =>
    value == null ? 'Consultar precio' : formatMxn(value);

const formatKm = (value: number | null) => (value == null ? 'No especificado' : `${value.toLocaleString('es-MX')} km`);
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex" />

    <section class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
            <div class="overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
                <img v-if="car.imageUrl" :src="car.imageUrl" :alt="car.title" referrerpolicy="no-referrer" class="aspect-[4/3] w-full object-cover" />
                <div v-else class="grid aspect-[4/3] place-items-center bg-muted text-muted-foreground">Sin foto</div>
            </div>

            <div class="space-y-4">
                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold text-white" :style="{ backgroundColor: car.platformColor || '#0f1729' }">
                    {{ car.platformName || 'Otra plataforma' }}
                </span>

                <div>
                    <p class="flex items-center gap-1 text-xs font-bold text-primary"><Sparkles class="h-3.5 w-3.5" /> Encontrado por búsqueda inteligente</p>
                    <h1 class="mt-2 text-2xl font-black leading-tight text-secondary sm:text-3xl">{{ car.title }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ car.year ?? 'Año no especificado' }} · {{ formatKm(car.km) }}
                    </p>
                </div>

                <p class="text-3xl font-black text-secondary">{{ formatPrice(car.price) }}</p>

                <p v-if="car.location" class="flex items-center gap-1 text-sm text-muted-foreground">
                    <MapPin class="h-4 w-4" /> {{ car.location }}
                </p>

                <div class="rounded-2xl border border-border bg-muted/40 p-4 text-xs text-muted-foreground">
                    Este auto proviene de una plataforma externa. Revisa los detalles directamente con el vendedor antes de cualquier pago.
                </div>

                <Button variant="hero" class="w-full gap-2 rounded-full" @click="externalSearch.openOriginal(car)">
                    <ExternalLink class="h-4 w-4" /> Ver anuncio original
                </Button>

                <p v-if="externalSearch.error.value" class="rounded-xl bg-destructive/10 p-3 text-xs font-semibold text-destructive">
                    {{ externalSearch.error.value }}
                </p>
            </div>
        </div>
    </section>

    <ExternalSearchLimitModal
        :open="externalSearch.showLimitModal.value"
        :status="externalSearch.status.value"
        @close="externalSearch.closeLimitModal"
        @reward="externalSearch.closeLimitModal(); rewardOpen = true"
    />

    <SearchRewardAdDialog :open="rewardOpen" @close="rewardOpen = false" @reward="externalSearch.refreshStatus" />
</template>
