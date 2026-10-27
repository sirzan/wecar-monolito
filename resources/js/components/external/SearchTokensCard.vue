<script setup lang="ts">
import { computed } from 'vue';
import { ArrowRight, Coins, Globe, ShieldCheck, Sparkles } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import type { ExternalSearchStatus } from '@/types/external';

const props = defineProps<{
    status: ExternalSearchStatus | null;
    onSearch?: () => void;
}>();

const subscribed = computed(() => props.status?.subscribed ?? false);
const unlimited = computed(() => props.status?.unlimited ?? false);
const isAdmin = computed(() => props.status?.is_admin ?? false);
const balance = computed(() => props.status?.token_balance ?? 0);
const hasCoupon = computed(() => props.status?.has_coupon ?? false);
</script>

<template>
    <div class="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-soft">
        <div class="relative overflow-hidden bg-gradient-dark px-5 py-5 text-white sm:px-8 sm:py-6">
            <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-primary/25 blur-2xl" />
            <div class="relative flex items-center gap-3">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-primary text-primary-foreground shadow-soft">
                    <Globe class="h-5 w-5" />
                </div>
                <h3 class="relative text-xl font-black leading-tight sm:text-2xl">Si no lo ves aquí, lo buscamos por ti.</h3>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-4 p-5 sm:p-6">
            <p class="text-sm text-muted-foreground">
                <template v-if="unlimited">
                    {{ isAdmin ? 'Acceso administrador: búsquedas y vistas ilimitadas.' : 'Membresía activa: búsquedas y vistas ilimitadas.' }}
                </template>
                <template v-else>
                    3 búsquedas gratis al día. Después, con tokens desde $49 MXN o membresía desde $499 MXN al mes.
                    <span v-if="hasCoupon" class="font-bold text-primary"> · 50% con cupón TKNS50</span>
                </template>
            </p>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-secondary">
                <span v-if="!unlimited" class="rounded-full bg-muted px-3 py-1">Tokens: {{ balance }}</span>
                <span class="rounded-full bg-muted px-3 py-1">{{ unlimited ? (isAdmin ? 'Acceso admin' : 'Ilimitado') : (subscribed ? 'Membresía activa' : 'Sin membresía') }}</span>
            </div>

            <div class="mt-auto grid gap-2 sm:grid-cols-2">
                <Button variant="outline" class="gap-2" @click="onSearch?.()">
                    <Sparkles class="h-4 w-4" /> Buscar en otras plataformas
                </Button>
                <Button v-if="!unlimited" as="a" href="/panel/membresia" variant="hero" class="gap-2">
                    <Coins class="h-4 w-4" /> {{ subscribed ? 'Ver membresía' : 'Suscribirme' }}
                    <ArrowRight class="h-4 w-4" />
                </Button>
                <Button v-else variant="secondary" class="gap-2" disabled>
                    <ShieldCheck class="h-4 w-4" /> Acceso ilimitado
                </Button>
            </div>
        </div>
    </div>
</template>
