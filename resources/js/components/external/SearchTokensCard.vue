<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArrowRight, CheckCircle2, Globe, Link2, Sparkles } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { formatMxn } from '@/lib/currency';
import type { ExternalPlatform, ExternalSearchStatus } from '@/types/external';

const props = defineProps<{
    status: ExternalSearchStatus | null;
    onSearch?: () => void;
}>();

const subscribed = computed(() => props.status?.subscribed ?? false);
const unlimited = computed(() => props.status?.unlimited ?? false);
const isAdmin = computed(() => props.status?.is_admin ?? false);
const balance = computed(() => props.status?.token_balance ?? 0);
const hasCoupon = computed(() => props.status?.has_coupon ?? false);
const hasFullAccess = computed(() => subscribed.value || unlimited.value);
const platforms = computed<ExternalPlatform[]>(() => props.status?.platforms ?? []);
const activeIndex = ref(0);
const direction = ref(1);
const failedLogos = ref<Record<string, boolean>>({});
let timer: ReturnType<typeof setInterval> | null = null;

const activePlatform = computed(() => platforms.value[activeIndex.value] ?? null);
const ballPosition = computed(() => {
    if (platforms.value.length <= 1) return 50;

    return (activeIndex.value / (platforms.value.length - 1)) * 100;
});

const startAnimation = () => {
    stopAnimation();

    if (hasFullAccess.value || platforms.value.length < 2) return;

    timer = setInterval(() => {
        if (activeIndex.value >= platforms.value.length - 1) direction.value = -1;
        if (activeIndex.value <= 0) direction.value = 1;
        activeIndex.value += direction.value;
    }, 2000);
};

const stopAnimation = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

watch(
    () => [hasFullAccess.value, platforms.value.length],
    () => {
        activeIndex.value = 0;
        direction.value = 1;
        startAnimation();
    },
);

onMounted(startAnimation);
onBeforeUnmount(stopAnimation);
</script>

<template>
    <div class="overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
        <div class="relative overflow-hidden bg-secondary px-4 py-4 text-white sm:px-6">
            <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-primary/20 blur-2xl" />
            <div class="relative flex items-center gap-3">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-primary text-primary-foreground shadow-soft">
                    <Globe class="h-4 w-4" />
                </div>
                <h3 class="relative text-lg font-black leading-tight text-white sm:text-xl">Si no lo ves aquí, lo buscamos por ti.</h3>
            </div>
        </div>

        <div v-if="hasFullAccess" class="flex flex-col items-center justify-center gap-3 px-5 py-6 text-center sm:py-7">
            <CheckCircle2 class="h-9 w-9 text-emerald-500" />
            <div>
                <p class="text-base font-black text-secondary sm:text-lg">
                    {{ isAdmin ? 'Acceso administrador activo' : 'Ya cuentas con membresía activa' }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground sm:text-sm">Búsquedas, vistas y anuncios externos sin límite.</p>
            </div>
            <Button variant="hero" class="mt-1 h-10 gap-2 rounded-full px-6 text-sm font-black" @click="onSearch?.()">
                <Sparkles class="h-4 w-4" /> Buscar ahora <ArrowRight class="h-4 w-4" />
            </Button>
        </div>

        <div v-else class="grid gap-0 lg:grid-cols-[1.1fr_1px_1fr]">
            <div class="flex flex-col justify-center border-b border-border px-4 py-4 sm:px-6 lg:border-b-0">
                <div v-if="platforms.length" class="relative px-2">
                    <div class="flex items-center justify-between gap-1">
                        <div
                            v-for="(platform, index) in platforms"
                            :key="platform.id"
                            class="flex min-w-0 flex-1 flex-col items-center gap-1 transition-all duration-300"
                            :class="index === activeIndex ? 'scale-105 opacity-100' : 'opacity-45 grayscale'"
                        >
                            <span class="grid h-12 w-12 place-items-center rounded-full border border-border bg-white p-2 shadow-soft sm:h-14 sm:w-14">
                                <img
                                    v-if="!failedLogos[platform.id]"
                                    :src="`/images/platforms/${platform.id}.svg`"
                                    :alt="platform.name"
                                    class="h-full w-full object-contain"
                                    loading="lazy"
                                    @error="failedLogos[platform.id] = true"
                                />
                                <span v-else class="text-[10px] font-black leading-none text-secondary">{{ platform.initials }}</span>
                            </span>
                            <span class="hidden max-w-full truncate text-[10px] font-bold text-secondary sm:block">{{ platform.name }}</span>
                        </div>
                    </div>
                    <div class="relative mt-3 h-1 rounded-full bg-border">
                        <span
                            class="absolute top-1/2 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary shadow-[0_0_0_4px_rgba(249,115,22,0.16)] transition-all duration-500 ease-out"
                            :style="{ left: `${ballPosition}%` }"
                        />
                    </div>
                    <p class="mt-2 truncate text-center text-[11px] font-bold text-primary">{{ activePlatform?.name }}</p>
                </div>
                <p v-else class="text-center text-xs font-semibold text-muted-foreground">Mercado Libre · Seminuevos · Kavak</p>
            </div>

            <div class="hidden bg-border lg:block" />

            <div class="flex flex-col justify-center px-4 py-4 sm:px-6">
                <p class="text-xs font-semibold leading-relaxed text-secondary sm:text-sm">
                    Búsquedas diarias limitadas. Después, con tokens desde
                    <strong class="text-primary">{{ formatMxn(49) }}</strong> o suscríbete desde
                    <strong class="text-primary">{{ formatMxn(499) }}</strong> al mes.
                    <span v-if="hasCoupon" class="font-bold text-primary"> Cupón TKNS50: 50% en tokens.</span>
                </p>

                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] font-semibold text-secondary">
                    <span class="rounded-full bg-muted px-2.5 py-0.5">Tokens: {{ balance }}</span>
                    <span class="rounded-full bg-muted px-2.5 py-0.5">{{ subscribed ? 'Membresía activa' : 'Sin membresía' }}</span>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    <Link
                        href="/panel/membresia"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-full border border-primary/30 bg-accent/50 px-4 text-xs font-black text-primary transition hover:bg-accent"
                    >
                        <Link2 class="h-3.5 w-3.5" />
                        {{ subscribed ? 'Ver membresía' : 'Suscríbete' }}
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                    <Button variant="hero" class="h-10 gap-1.5 rounded-full text-xs font-black" @click="onSearch?.()">
                        <Sparkles class="h-3.5 w-3.5" /> Buscar en otras plataformas
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
