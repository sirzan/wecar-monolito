<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Landmark, LayoutDashboard, Menu, Plus, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Logo from '@/components/site/Logo.vue';
import NotificationBell from '@/components/site/NotificationBell.vue';
import NavbarInstallApp from '@/components/site/NavbarInstallApp.vue';
import { useFavoritesStore } from '@/stores/favorites';

const page = usePage<{ auth: { user: { id: string; name: string } | null } }>();
const open = ref(false);
const favorites = useFavoritesStore();

const links = [
    { href: '/comprar', label: 'Comprar' },
    { href: '/credito', label: 'Crédito' },
];

const isActive = (href: string) => page.url.startsWith(href);

onMounted(() => void favorites.bootstrap());
</script>

<template>
    <header class="safe-top sticky top-0 z-50 w-full border-b border-border/60 bg-background/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2 sm:px-6 lg:gap-4 lg:py-3">
            <Logo />

            <div class="hidden items-center gap-2 lg:flex">
                <template v-if="page.props.auth.user">
                    <Link
                        href="/panel/favoritos"
                        aria-label="Favoritos"
                        class="relative grid h-10 w-10 shrink-0 place-items-center rounded-full border border-border bg-background text-secondary transition hover:border-primary/40 hover:text-primary"
                        :class="isActive('/panel/favoritos') ? 'border-primary bg-accent text-primary' : ''"
                    >
                        <Heart class="h-4 w-4" />
                        <span
                            v-if="favorites.count > 0"
                            class="absolute -right-1 -top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[9px] font-black text-primary-foreground"
                        >
                            {{ favorites.count > 99 ? '99+' : favorites.count }}
                        </span>
                    </Link>
                    <NotificationBell />
                </template>

                <Button
                    as="a"
                    href="/comprar"
                    variant="secondary"
                    size="pill"
                    class="gap-1.5 whitespace-nowrap text-[13.2px]"
                    :class="isActive('/comprar') ? 'ring-2 ring-primary/30' : ''"
                >
                    Comprar
                </Button>
                <Button
                    as="a"
                    href="/credito"
                    variant="secondary"
                    size="pill"
                    class="gap-1.5 whitespace-nowrap text-[13.2px]"
                    :class="isActive('/credito') ? 'ring-2 ring-primary/30' : ''"
                >
                    <Landmark class="h-3.5 w-3.5" /> Crédito
                </Button>

                <template v-if="page.props.auth.user">
                    <Button as="a" href="/vende" variant="secondary" size="pill" class="gap-1.5 whitespace-nowrap text-[13.2px]">
                        <Plus class="h-3.5 w-3.5" /> Publicar
                    </Button>
                    <Button as="a" href="/panel" variant="hero" size="pill" class="gap-1.5 whitespace-nowrap text-[13.2px]">
                        <LayoutDashboard class="h-4 w-4" /> Mi cuenta
                    </Button>
                </template>
                <template v-else>
                    <Button as="a" href="/login" variant="ghost" size="pill" class="whitespace-nowrap text-[13.2px]">Iniciar sesión</Button>
                    <Button as="a" href="/registro" variant="hero" size="pill" class="whitespace-nowrap text-[13.2px]">Registrarse</Button>
                </template>
            </div>

            <div class="flex shrink-0 items-center gap-1 lg:hidden">
                <Link
                    v-if="page.props.auth.user"
                    href="/panel/favoritos"
                    aria-label="Favoritos"
                    class="relative grid h-10 w-10 place-items-center rounded-full text-secondary transition hover:bg-accent hover:text-primary"
                >
                    <Heart class="h-5 w-5" />
                    <span
                        v-if="favorites.count > 0"
                        class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[9px] font-black text-primary-foreground"
                    >
                        {{ favorites.count > 99 ? '99+' : favorites.count }}
                    </span>
                </Link>
                <NotificationBell v-if="page.props.auth.user" />
                <button
                    type="button"
                    aria-label="Menú"
                    class="grid h-10 w-10 place-items-center rounded-full bg-accent text-primary"
                    @click="open = !open"
                >
                    <X v-if="open" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>
        </div>

        <NavbarInstallApp />

        <div v-if="open" class="border-t border-border/60 bg-background lg:hidden">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 px-6 py-4">
                <template v-for="link in links" :key="link.href">
                    <Link
                        :href="link.href"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-2.5 text-[15.4px] font-semibold"
                        :class="isActive(link.href) ? 'bg-accent text-primary' : 'text-muted-foreground hover:bg-accent/60'"
                        @click="open = false"
                    >
                        <Landmark v-if="link.href === '/credito'" class="h-4 w-4" />
                        {{ link.label }}
                    </Link>
                </template>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <template v-if="page.props.auth.user">
                        <Button as="a" href="/panel" variant="outline" size="pill" class="w-full text-[15.4px]">Mi cuenta</Button>
                        <Button as="a" href="/vende" variant="hero" size="pill" class="w-full text-[15.4px]">Publicar</Button>
                    </template>
                    <template v-else>
                        <Button as="a" href="/login" variant="outline" size="pill" class="w-full text-[15.4px]">Iniciar sesión</Button>
                        <Button as="a" href="/registro" variant="hero" size="pill" class="w-full text-[15.4px]">Registrarse</Button>
                    </template>
                </div>
            </div>
        </div>
    </header>
</template>
