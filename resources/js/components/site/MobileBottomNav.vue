<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Home, Landmark, PlusCircle, Search, User } from '@lucide/vue';

const page = usePage<{ auth: { user: unknown | null } }>();
const url = computed(() => page.url.split('?')[0]);

const items = computed(() => [
    { href: '/', label: 'Inicio', icon: Home, end: true },
    { href: '/comprar', label: 'Buscar', icon: Search },
    { href: '/vende', label: 'Vender', icon: PlusCircle },
    { href: '/credito', label: 'Crédito', icon: Landmark },
    { href: page.props.auth.user ? '/panel' : '/login', label: 'Cuenta', icon: User },
]);

const hidden = computed(() => url.value.startsWith('/admin') || url.value.startsWith('/panel'));

const isActive = (href: string, end = false) => {
    if (end) return url.value === '/';
    return url.value === href || url.value.startsWith(`${href}/`);
};
</script>

<template>
    <nav
        v-if="!hidden"
        aria-label="Navegación principal"
        class="safe-bottom fixed inset-x-0 bottom-0 z-50 border-t border-border/60 bg-background/95 backdrop-blur-md lg:hidden"
    >
        <ul class="grid h-16 w-full grid-cols-5">
            <li v-for="item in items" :key="item.label" class="min-w-0">
                <Link
                    :href="item.href"
                    :class="[
                        'touch-target flex h-full flex-col items-center justify-center gap-0.5 text-[11px] font-semibold transition-colors',
                        isActive(item.href, item.end) ? 'text-primary' : 'text-muted-foreground',
                    ]"
                >
                    <component :is="item.icon" class="h-5 w-5 shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
