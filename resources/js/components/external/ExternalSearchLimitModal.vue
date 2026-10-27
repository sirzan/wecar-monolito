<script setup lang="ts">
import { computed, ref } from 'vue';
import { CheckCircle2, Coins, Globe, PlayCircle, ShieldCheck, Sparkles } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import type { ExternalSearchStatus } from '@/types/external';

const props = defineProps<{ open: boolean; status: ExternalSearchStatus | null }>();
const emit = defineEmits<{ close: []; reward: [] }>();

const subscribed = computed(() => props.status?.subscribed ?? false);
const unlimited = computed(() => props.status?.unlimited ?? false);
const balance = computed(() => props.status?.token_balance ?? 0);
const hasCoupon = computed(() => props.status?.has_coupon ?? false);
const price = 499;

const tokenPackages = [
    { key: 'search_tokens_5', label: '5 tokens', price: 49 },
    { key: 'search_tokens_10', label: '10 tokens', price: 79 },
];
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[95] grid place-items-center bg-secondary/70 p-3 backdrop-blur-sm" @click.self="emit('close')">
            <div class="w-full max-w-md rounded-3xl bg-card p-6 shadow-elegant">
                <div class="text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-primary/10 text-primary">
                        <Globe class="h-8 w-8" />
                    </div>
                    <h2 class="mt-4 text-xl font-black text-secondary">Obtén más búsquedas</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ unlimited ? 'Tu cuenta tiene acceso ilimitado a búsquedas y anuncios externos.' : 'Usaste tus búsquedas o vistas gratuitas de hoy. Compra tokens o activa la membresía para continuar.' }}
                    </p>
                    <p v-if="subscribed" class="mt-2 text-xs font-bold text-emerald-600">Tu membresía está activa.</p>
                    <p v-else class="mt-2 text-xs font-semibold text-secondary">Saldo actual: {{ balance }} tokens</p>
                    <p v-if="hasCoupon" class="mt-1 text-xs font-bold text-primary">Cupón TKNS50 disponible: 50% en tokens.</p>
                </div>

                <div v-if="unlimited" class="mt-5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center text-sm font-bold text-emerald-700">
                    No necesitas tokens ni membresía. Puedes buscar y abrir anuncios sin límite.
                </div>

                <div v-if="!unlimited" class="mt-5 rounded-2xl border border-primary/30 bg-gradient-to-br from-primary/5 via-card to-accent/20 p-4">
                    <div class="flex items-center justify-between border-b border-border/60 pb-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Membresía</p>
                            <p class="text-sm font-black text-secondary">Búsqueda Inteligente Ilimitada</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-black text-primary">${{ price }}</p>
                            <p class="text-[10px] font-bold text-muted-foreground">MXN / 30 días</p>
                        </div>
                    </div>
                    <ul class="mt-3 space-y-2 text-xs text-secondary">
                        <li class="flex items-center gap-2"><CheckCircle2 class="h-4 w-4 text-emerald-500" /> Búsquedas y vistas ilimitadas</li>
                        <li class="flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-primary" /> Acceso directo a los anuncios</li>
                    </ul>
                </div>

                <div v-if="!unlimited" class="mt-4 grid grid-cols-2 gap-2">
                    <Link
                        v-for="pack in tokenPackages"
                        :key="pack.key"
                        href="/panel/membresia"
                        class="rounded-xl border border-border p-3 text-center transition hover:border-primary/50"
                    >
                        <p class="text-sm font-black text-secondary">{{ pack.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="hasCoupon" class="line-through">${{ pack.price }}</span>
                            <span class="font-black text-primary"> ${{ hasCoupon ? (pack.price / 2).toFixed(2) : pack.price }} MXN</span>
                        </p>
                    </Link>
                </div>

                <div class="mt-4 space-y-2">
                    <Button v-if="!unlimited" variant="outline" class="w-full gap-2" @click="emit('reward')">
                        <PlayCircle class="h-4 w-4" /> Ver anuncio y recibir 1 token gratis
                    </Button>
                    <Button v-if="!unlimited" as="a" href="/panel/membresia" variant="hero" class="w-full gap-2" @click="emit('close')">
                        <Sparkles class="h-4 w-4" /> Obtener membresía por ${{ price }} MXN
                    </Button>
                    <Button variant="ghost" class="w-full text-xs" @click="emit('close')">Continuar viendo el catálogo</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
