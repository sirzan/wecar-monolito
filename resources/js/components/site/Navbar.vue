<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Menu, Plus, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Logo from '@/components/site/Logo.vue';
import NotificationBell from '@/components/site/NotificationBell.vue';
import NavbarInstallApp from '@/components/site/NavbarInstallApp.vue';

const page = usePage<{ auth: { user: { id: string; name: string } | null } }>();
const open = ref(false);

const links = [
    { href: '/comprar', label: 'Comprar' },
    { href: '/vende', label: 'Vende tu auto' },
    { href: '/credito', label: 'Crédito' },
    { href: '/garantia', label: 'Garantía' },
    { href: '/certificaciones', label: 'Certificaciones' },
    {
        href: 'https://motorevs.com.mx/?gad_source=1&gad_campaignid=22786191493',
        label: 'Cuida tu auto',
        external: true,
    },
];

const isActive = (href: string) => !href.startsWith('http') && page.url.startsWith(href);
</script>

<template>
    <header class="safe-top sticky top-0 z-50 w-full border-b border-border/60 bg-background/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2 sm:px-6 lg:gap-4 lg:py-3">
            <Logo />

            <nav class="hidden items-center gap-1 lg:flex">
                <template v-for="link in links" :key="link.href">
                    <a
                        v-if="link.external"
                        :href="link.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="whitespace-nowrap rounded-lg px-3 py-2 text-[13px] font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-primary"
                    >
                        {{ link.label }}
                    </a>
                    <Link
                        v-else
                        :href="link.href"
                        class="whitespace-nowrap rounded-lg px-3 py-2 text-[13px] font-semibold transition-colors hover:bg-accent hover:text-primary"
                        :class="isActive(link.href) ? 'text-primary' : 'text-muted-foreground'"
                    >
                        {{ link.label }}
                    </Link>
                </template>
            </nav>

            <div class="hidden items-center gap-2 lg:flex">
                <template v-if="page.props.auth.user">
                    <NotificationBell />
                    <Button as="a" href="/vende" variant="secondary" size="pill" class="gap-1.5 whitespace-nowrap text-xs">
                        <Plus class="h-3.5 w-3.5" /> Publicar
                    </Button>
                    <Button as="a" href="/panel" variant="hero" size="pill" class="gap-1.5 whitespace-nowrap text-xs">
                        <LayoutDashboard class="h-4 w-4" /> Mi cuenta
                    </Button>
                </template>
                <template v-else>
                    <Button as="a" href="/login" variant="ghost" size="pill" class="whitespace-nowrap text-xs">Iniciar sesión</Button>
                    <Button as="a" href="/registro" variant="hero" size="pill" class="whitespace-nowrap text-xs">Registrarse</Button>
                </template>
            </div>

            <div class="flex shrink-0 items-center gap-1 lg:hidden">
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
                    <a
                        v-if="link.external"
                        :href="link.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-accent/60"
                        @click="open = false"
                    >
                        {{ link.label }}
                    </a>
                    <Link
                        v-else
                        :href="link.href"
                        class="rounded-xl px-3 py-2.5 text-sm font-semibold"
                        :class="isActive(link.href) ? 'bg-accent text-primary' : 'text-muted-foreground hover:bg-accent/60'"
                        @click="open = false"
                    >
                        {{ link.label }}
                    </Link>
                </template>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <template v-if="page.props.auth.user">
                        <Button as="a" href="/panel" variant="outline" size="pill" class="w-full">Mi cuenta</Button>
                        <Button as="a" href="/vende" variant="hero" size="pill" class="w-full">Publicar</Button>
                    </template>
                    <template v-else>
                        <Button as="a" href="/login" variant="outline" size="pill" class="w-full">Iniciar sesión</Button>
                        <Button as="a" href="/registro" variant="hero" size="pill" class="w-full">Registrarse</Button>
                    </template>
                </div>
            </div>
        </div>
    </header>
</template>
