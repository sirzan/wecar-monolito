<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import {
    Boxes,
    Building2,
    Car,
    ChevronDown,
    CreditCard,
    ExternalLink,
    FileText,
    FileWarning,
    GitBranch,
    Inbox,
    KeyRound,
    LayoutDashboard,
    LogOut,
    Megaphone,
    Menu,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    Sparkles,
    Tag,
    TicketPercent,
    Users,
    X,
} from '@lucide/vue';
import { cn } from '@/lib/utils';

interface UserInfo {
    id: string;
    name: string;
    email: string;
    avatar_url?: string | null;
    roles: string[];
    permissions: string[];
}

const page = usePage<{
    auth: { user: UserInfo | null };
    flash: { success?: string; error?: string };
}>();

const current = computed(() => page.url.split('?')[0]);
const user = computed(() => page.props.auth.user);

const mobileMenuOpen = ref(false);

const mainItems = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { label: 'Créditos', href: '/admin/creditos', icon: CreditCard },
    { label: 'Autos', href: '/admin/autos', icon: Car },
    { label: 'Financieras', href: '/admin/financieras', icon: Building2 },
    { label: 'Usuarios', href: '/admin/usuarios', icon: Users },
    { label: 'Agencias', href: '/admin/agencias', icon: Building2 },
    { label: 'Planes', href: '/admin/planes', icon: Sparkles },
    { label: 'Cupones', href: '/admin/cupones', icon: TicketPercent },
    { label: 'Publicidad', href: '/admin/publicidad', icon: Megaphone },
    { label: 'Pagos', href: '/admin/pagos', icon: Receipt },
    { label: 'Reportes', href: '/admin/reportes', icon: FileWarning },
    { label: 'Leads', href: '/admin/leads', icon: Inbox },
];

const groups = ref([
    {
        label: 'Configuración',
        icon: Settings,
        open: true,
        items: [
            { label: 'Ajustes Generales', href: '/admin/configuracion', icon: Settings },
        ],
    },
    {
        label: 'Catálogos de Vehículos',
        icon: Tag,
        open: true,
        items: [
            { label: 'Marcas (Brands)', href: '/admin/catalogos/brands', icon: Tag },
            { label: 'Modelos (Models)', href: '/admin/catalogos/models', icon: Boxes },
            { label: 'Versiones (Versions)', href: '/admin/catalogos/versions', icon: GitBranch },
            { label: 'Estatus (Statuses)', href: '/admin/catalogos/statuses', icon: ShieldCheck },
        ],
    },
]);

const isItemActive = (href: string) => {
    if (href === '/admin') return current.value === '/admin';
    return current.value.startsWith(href);
};

const initials = computed(() => {
    const name = user.value?.name || user.value?.email || 'Admin';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((n) => n.charAt(0))
        .join('')
        .toUpperCase();
});

const userRole = computed(() => user.value?.roles?.[0] || 'superadmin');

const logout = () => router.post('/logout');

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="theme-admin dark flex min-h-screen bg-background text-foreground">
        <!-- Desktop Sidebar -->
        <aside class="sticky top-0 hidden h-screen w-[260px] shrink-0 border-r border-sidebar-border bg-sidebar md:flex md:flex-col">
            <!-- Brand -->
            <div class="flex h-16 items-center gap-2.5 border-b border-sidebar-border px-5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground shadow-sm">
                    W
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-sm font-semibold tracking-tight text-foreground">Wecar</span>
                    <span class="text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Admin Panel</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
                <div>
                    <p class="px-3 pb-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                        Overview
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in mainItems" :key="item.href">
                            <Link
                                :href="item.href"
                                :class="
                                    cn(
                                        'group relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        isItemActive(item.href)
                                            ? 'bg-sidebar-accent text-foreground shadow-[inset_0_0_0_1px_oklch(0.78_0.16_65/0.35)]'
                                            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground',
                                    )
                                "
                            >
                                <span
                                    v-if="isItemActive(item.href)"
                                    class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-primary"
                                />
                                <component
                                    :is="item.icon"
                                    class="h-4 w-4 shrink-0 transition-colors"
                                    :class="isItemActive(item.href) ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'"
                                />
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Grupos colapsables -->
                <div v-for="group in groups" :key="group.label" class="pt-2">
                    <button
                        type="button"
                        class="group flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground transition-colors hover:text-foreground"
                        @click="group.open = !group.open"
                    >
                        <span class="flex items-center gap-2">
                            <component :is="group.icon" class="h-3.5 w-3.5 opacity-70" />
                            {{ group.label }}
                        </span>
                        <ChevronDown
                            class="h-3.5 w-3.5 transition-transform duration-200"
                            :class="group.open ? 'rotate-180' : ''"
                        />
                    </button>

                    <div v-show="group.open" class="mt-1 space-y-0.5 pl-2">
                        <Link
                            v-for="sub in group.items"
                            :key="sub.href"
                            :href="sub.href"
                            :class="
                                cn(
                                    'group relative flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition-colors',
                                    isItemActive(sub.href)
                                        ? 'bg-sidebar-accent text-foreground shadow-[inset_0_0_0_1px_oklch(0.78_0.16_65/0.35)]'
                                        : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground',
                                )
                            "
                        >
                            <span
                                v-if="isItemActive(sub.href)"
                                class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full bg-primary"
                            />
                            <component
                                :is="sub.icon"
                                class="h-3.5 w-3.5 shrink-0 transition-colors"
                                :class="isItemActive(sub.href) ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'"
                            />
                            <span class="truncate">{{ sub.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Footer User -->
            <div class="border-t border-sidebar-border p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-sidebar-accent">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary to-amber-700 text-sm font-semibold text-primary-foreground shadow-xs">
                        {{ initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <p class="truncate text-sm font-medium text-foreground">{{ user?.name ?? 'Admin Wecar' }}</p>
                            <span class="inline-flex items-center rounded-full border border-primary/30 bg-primary/15 px-1.5 py-0 text-[9px] font-semibold uppercase tracking-wider text-primary">
                                {{ userRole }}
                            </span>
                        </div>
                        <p class="truncate text-xs text-muted-foreground">{{ user?.email }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-background/40 hover:text-foreground"
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Topbar -->
            <header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-border bg-background/90 px-4 backdrop-blur-md sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-border text-foreground hover:bg-accent md:hidden"
                        aria-label="Abrir menú"
                        @click="mobileMenuOpen = true"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                    <div>
                        <p class="text-sm font-bold text-foreground">{{ user?.name ?? 'Panel Admin' }}</p>
                        <p class="text-xs text-muted-foreground">Wecar Control Center</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="/"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted-foreground transition hover:border-primary/40 hover:text-foreground"
                    >
                        <span>Ver sitio público</span>
                        <ExternalLink class="h-3.5 w-3.5" />
                    </a>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <!-- Mobile Drawer -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm md:hidden"
                @click="mobileMenuOpen = false"
            />
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="mobileMenuOpen"
                class="fixed inset-y-0 left-0 z-50 flex w-[85vw] max-w-sm flex-col border-r border-sidebar-border bg-sidebar md:hidden"
            >
                <div class="flex items-center justify-between border-b border-sidebar-border p-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground shadow-sm">
                            W
                        </div>
                        <span class="text-sm font-semibold text-foreground">Wecar Admin</span>
                    </div>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-accent"
                        aria-label="Cerrar menú"
                        @click="mobileMenuOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <nav class="flex-1 space-y-4 overflow-y-auto p-4">
                    <ul class="space-y-1">
                        <li v-for="item in mainItems" :key="item.href">
                            <Link
                                :href="item.href"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        isItemActive(item.href)
                                            ? 'bg-sidebar-accent text-primary'
                                            : 'text-sidebar-foreground hover:bg-sidebar-accent',
                                    )
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>

                    <div v-for="group in groups" :key="group.label" class="pt-2">
                        <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ group.label }}
                        </p>
                        <div class="space-y-1">
                            <Link
                                v-for="sub in group.items"
                                :key="sub.href"
                                :href="sub.href"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        isItemActive(sub.href)
                                            ? 'bg-sidebar-accent text-primary'
                                            : 'text-sidebar-foreground hover:bg-sidebar-accent',
                                    )
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <component :is="sub.icon" class="h-4 w-4 shrink-0" />
                                <span>{{ sub.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border pt-4">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-sidebar-accent hover:text-destructive"
                            @click="logout"
                        >
                            <LogOut class="h-4 w-4" />
                            <span>Cerrar sesión</span>
                        </button>
                    </div>
                </nav>
            </aside>
        </Transition>

        <Toaster position="top-center" rich-colors theme="dark" />
    </div>
</template>