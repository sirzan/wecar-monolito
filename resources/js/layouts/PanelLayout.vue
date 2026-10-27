<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import {
    Building2,
    Car,
    CheckCircle2,
    CreditCard,
    FileText,
    Heart,
    Inbox,
    LayoutDashboard,
    Loader2,
    LogOut,
    Menu,
    Pencil,
    Plus,
    Settings,
    ShieldCheck,
    Sparkles,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import Navbar from '@/components/site/Navbar.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import { cn } from '@/lib/utils';

interface AgencySummary {
    id: string;
    slug: string;
    name: string;
}

interface CurrentAgency extends AgencySummary {
    logo: string | null;
    plan: string;
    max_cars: number;
    role: string | null;
}

interface AuthUser {
    id: string;
    name: string;
    email: string;
    avatar_url?: string | null;
    didit_status?: string | null;
    roles: string[];
    permissions: string[];
}

const page = usePage<{
    auth: { user: AuthUser | null };
    currentAgency: CurrentAgency | null;
    agencies: AgencySummary[];
    flash: { success?: string; error?: string };
}>();

const user = computed(() => page.props.auth.user);
const currentAgency = computed(() => page.props.currentAgency);
const agencies = computed(() => page.props.agencies ?? []);
const currentPath = computed(() => page.url.split('?')[0]);

const mobileMenuOpen = ref(false);
const isVerifying = ref(false);
const isUploadingAvatar = ref(false);
const avatarInput = ref<HTMLInputElement | null>(null);

const displayName = computed(() => user.value?.name || user.value?.email?.split('@')[0] || 'Mi cuenta');
const initial = computed(() => displayName.value.charAt(0).toUpperCase());
const diditStatus = computed(() => user.value?.didit_status || 'unverified');
const diditPending = computed(() => ['pending', 'in_review'].includes(diditStatus.value));
const diditFailed = computed(() => ['declined', 'expired'].includes(diditStatus.value));

const personalItems = [
    { label: 'Resumen', href: '/panel', icon: LayoutDashboard },
    { label: 'Mis publicaciones', href: '/panel/publicaciones', icon: Car },
    { label: 'Publicar auto', href: '/vende', icon: Plus },
    { label: 'Favoritos', href: '/panel/favoritos', icon: Heart },
    { label: 'Membresía', href: '/panel/membresia', icon: Sparkles },
    { label: 'Mis trámites', href: '/panel/tramites', icon: FileText },
    { label: 'Perfil crediticio', href: '/panel/perfil-crediticio', icon: ShieldCheck },
    { label: 'Facturación', href: '/panel/facturacion', icon: CreditCard },
    { label: 'Configuración', href: '/panel/configuracion', icon: Settings },
];

const agencyBase = computed(() => (currentAgency.value ? `/panel/agencia/${currentAgency.value.slug}` : null));

const agencyItems = computed(() => {
    if (!agencyBase.value) return [];
    return [
        { label: 'Resumen agencia', href: `${agencyBase.value}/resumen`, icon: LayoutDashboard },
        { label: 'Inventario', href: `${agencyBase.value}/inventario`, icon: Car },
        { label: 'Leads', href: `${agencyBase.value}/leads`, icon: Inbox },
        { label: 'Equipo', href: `${agencyBase.value}/equipo`, icon: Users },
        { label: 'Perfil público', href: `${agencyBase.value}/perfil`, icon: Building2 },
        { label: 'Facturación agencia', href: `${agencyBase.value}/facturacion`, icon: CreditCard },
        { label: 'Configuración agencia', href: `${agencyBase.value}/configuracion`, icon: Settings },
    ];
});

const switchAgency = (event: Event) => {
    const agencyId = (event.target as HTMLSelectElement).value;
    if (!agencyId) return;
    router.post('/panel/agencia/switch', { agency_id: agencyId });
};

const handleAvatarFile = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        toast.error('Por favor selecciona un archivo de imagen');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        toast.error('La imagen debe pesar menos de 5 MB');
        return;
    }

    const formData = new FormData();
    formData.append('avatar', file);

    isUploadingAvatar.value = true;
    router.post('/panel/avatar', formData, {
        onFinish: () => {
            isUploadingAvatar.value = false;
        },
        onError: () => {
            toast.error('No se pudo subir la foto de perfil');
        },
    });
};

const deleteAvatar = () => {
    if (!confirm('¿Quieres eliminar tu foto de perfil?')) return;
    router.delete('/panel/avatar', {
        onError: () => toast.error('No se pudo eliminar la foto'),
    });
};

const handleVerifyIdentity = async () => {
    try {
        isVerifying.value = true;
        const res = await fetch('/api/didit/session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        const data = await res.json();
        if (data.url) {
            window.location.href = data.url;
        } else {
            toast.error(data.error || 'No se pudo iniciar la verificación de identidad.');
        }
    } catch {
        toast.error('Error de conexión al iniciar la verificación');
    } finally {
        isVerifying.value = false;
    }
};

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
    <div class="min-h-screen bg-muted/30">
        <!-- Barra de navegación pública superior -->
        <Navbar />

        <!-- Contenedor principal del panel -->
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-8">
            <!-- Header móvil compacto con botón de menú -->
            <div class="mb-5 lg:hidden">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-soft">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-border bg-background text-foreground transition hover:bg-accent"
                            aria-label="Abrir menú"
                            @click="mobileMenuOpen = true"
                        >
                            <Menu class="h-5 w-5" />
                        </button>

                        <div class="flex items-center gap-3">
                            <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-full border border-border bg-primary/10">
                                <img
                                    v-if="user?.avatar_url"
                                    :src="user.avatar_url"
                                    :alt="displayName"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else class="grid h-full w-full place-items-center font-bold text-primary">
                                    {{ initial }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-secondary">{{ displayName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ user?.email }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Badge
                            v-if="diditStatus === 'approved'"
                            variant="success"
                            size="sm"
                            class="gap-1 border-emerald-300 bg-emerald-50 text-emerald-700"
                        >
                            <ShieldCheck class="h-3 w-3" /> Verificado
                        </Badge>
                        <Badge
                            v-else-if="diditPending"
                            variant="outline"
                            size="sm"
                            class="gap-1 border-amber-300 bg-amber-50 text-amber-700"
                        >
                            En revisión
                        </Badge>
                        <Button
                            v-else
                            size="sm"
                            variant="outline"
                            class="h-8 gap-1 text-xs"
                            :disabled="isVerifying"
                            @click="handleVerifyIdentity"
                        >
                            <ShieldCheck class="h-3 w-3" /> {{ diditFailed ? 'Reintentar' : 'Verificar' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Grid de 2 columnas en Desktop -->
            <div class="grid gap-6 lg:grid-cols-[290px_1fr]">
                <!-- Desktop Sidebar -->
                <aside class="hidden space-y-5 lg:block">
                    <!-- Tarjeta de perfil del usuario -->
                    <div class="overflow-hidden rounded-3xl border border-border/70 bg-card p-6 text-center shadow-soft">
                        <div class="relative mx-auto h-24 w-24">
                            <div class="h-24 w-24 overflow-hidden rounded-full border-2 border-primary/20 bg-primary/10 shadow-sm">
                                <img
                                    v-if="user?.avatar_url"
                                    :src="user.avatar_url"
                                    :alt="displayName"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="grid h-full w-full place-items-center text-3xl font-black text-primary">
                                    {{ initial }}
                                </div>
                            </div>

                            <button
                                type="button"
                                title="Cambiar foto"
                                :disabled="isUploadingAvatar"
                                class="absolute -bottom-1 -right-1 grid h-8 w-8 place-items-center rounded-full bg-secondary text-secondary-foreground shadow-md ring-2 ring-card transition hover:scale-105 disabled:opacity-50"
                                @click="avatarInput?.click()"
                            >
                                <Loader2 v-if="isUploadingAvatar" class="h-3.5 w-3.5 animate-spin" />
                                <Pencil v-else class="h-3.5 w-3.5" />
                            </button>
                            <input
                                ref="avatarInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleAvatarFile"
                            />
                        </div>

                        <div v-if="user?.avatar_url" class="mt-2">
                            <button
                                type="button"
                                class="text-[11px] font-medium text-destructive hover:underline"
                                @click="deleteAvatar"
                            >
                                Eliminar foto
                            </button>
                        </div>

                        <h2 class="mt-3 truncate text-lg font-black text-secondary">{{ displayName }}</h2>
                        <p class="truncate text-xs text-muted-foreground">{{ user?.email }}</p>

                        <!-- Estado Didit -->
                        <div class="mt-4 border-t border-border/60 pt-4">
                            <div v-if="diditStatus === 'approved'" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                <CheckCircle2 class="h-4 w-4 text-emerald-600" /> Identidad verificada
                            </div>
                            <div v-else-if="diditPending" class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                {{ diditStatus === 'in_review' ? 'En revisión' : 'Verificación pendiente' }}
                            </div>
                            <div v-else class="space-y-2">
                                <p class="text-xs" :class="diditFailed ? 'font-semibold text-destructive' : 'text-muted-foreground'">
                                    {{ diditFailed ? 'No pudimos verificar tu identidad. Puedes intentarlo de nuevo.' : 'Verifica tu cuenta para generar más confianza.' }}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="w-full border-primary/40 text-primary hover:bg-primary/10"
                                    :disabled="isVerifying"
                                    @click="handleVerifyIdentity"
                                >
                                    <Loader2 v-if="isVerifying" class="h-4 w-4 animate-spin" />
                                    <ShieldCheck v-else class="h-4 w-4" />
                                    {{ diditFailed ? 'Reintentar verificación' : 'Verificar Identidad' }}
                                </Button>
                            </div>
                        </div>

                        <!-- Selector / badge de agencia si aplica -->
                        <div v-if="agencies.length" class="mt-4 border-t border-border/60 pt-4 text-left">
                            <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Agencia activa</p>
                            <select
                                v-if="agencies.length > 1"
                                class="h-9 w-full rounded-xl border border-input bg-background px-2.5 text-xs font-semibold text-secondary focus:border-primary focus:outline-none"
                                :value="currentAgency?.id"
                                @change="switchAgency"
                            >
                                <option v-for="agency in agencies" :key="agency.id" :value="agency.id">
                                    {{ agency.name }}
                                </option>
                            </select>
                            <div v-else-if="currentAgency" class="flex items-center gap-2 rounded-xl bg-accent/60 px-3 py-2 text-xs font-bold text-primary">
                                <Building2 class="h-4 w-4" /> {{ currentAgency.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de menú de navegación -->
                    <div class="overflow-hidden rounded-3xl border border-border/70 bg-card p-3 shadow-soft">
                        <nav class="space-y-1">
                            <Link
                                v-for="item in personalItems"
                                :key="item.href"
                                :href="item.href"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold transition-colors',
                                        currentPath === item.href
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-secondary hover:bg-accent/60 hover:text-primary',
                                    )
                                "
                            >
                                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>

                            <!-- Menú de la agencia -->
                            <template v-if="currentAgency && agencyItems.length">
                                <div class="my-2 border-t border-border/60 pt-2">
                                    <p class="px-4 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                        {{ currentAgency.name }}
                                    </p>
                                </div>
                                <Link
                                    v-for="item in agencyItems"
                                    :key="item.href"
                                    :href="item.href"
                                    :class="
                                        cn(
                                            'flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold transition-colors',
                                            currentPath === item.href
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'text-secondary hover:bg-accent/60 hover:text-primary',
                                        )
                                    "
                                >
                                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </template>

                            <div v-else class="my-2 border-t border-border/60 pt-2">
                                <Link
                                    href="/panel/agencia/crear"
                                    class="flex items-center gap-2 rounded-2xl border border-dashed border-border px-3.5 py-2.5 text-xs font-semibold text-muted-foreground transition hover:border-primary/50 hover:text-primary"
                                >
                                    <Building2 class="h-4 w-4" /> Crear o unirme a agencia
                                </Link>
                            </div>

                            <div class="my-2 border-t border-border/60 pt-2">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold text-muted-foreground transition hover:bg-accent hover:text-destructive"
                                    @click="logout"
                                >
                                    <LogOut class="h-4 w-4 shrink-0" />
                                    <span>Cerrar sesión</span>
                                </button>
                            </div>
                        </nav>
                    </div>
                </aside>

                <!-- Columna principal con el contenido de la vista actual -->
                <main class="min-w-0">
                    <slot />
                </main>
            </div>
        </div>

        <!-- Drawer móvil lateral -->
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
                class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm lg:hidden"
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
                class="fixed inset-y-0 left-0 z-50 flex w-[85vw] max-w-sm flex-col border-r border-border bg-card shadow-2xl lg:hidden"
            >
                <div class="flex items-center justify-between border-b border-border/60 p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 overflow-hidden rounded-full border border-border bg-primary/10">
                            <img
                                v-if="user?.avatar_url"
                                :src="user.avatar_url"
                                :alt="displayName"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="grid h-full w-full place-items-center font-bold text-primary">
                                {{ initial }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-secondary">{{ displayName }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ user?.email }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-accent hover:text-foreground"
                        aria-label="Cerrar menú"
                        @click="mobileMenuOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    <div v-if="diditStatus !== 'approved'" class="mb-3 px-1">
                        <div v-if="diditStatus === 'in_review'" class="rounded-xl bg-amber-50 px-3 py-2 text-center text-xs font-bold text-amber-700">
                            Verificación en revisión
                        </div>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            class="w-full border-primary/40 text-primary hover:bg-primary/10"
                            :disabled="isVerifying"
                            @click="handleVerifyIdentity"
                        >
                            <ShieldCheck class="h-4 w-4" />
                            {{ diditPending ? 'Continuar verificación' : diditFailed ? 'Reintentar verificación' : 'Verificar Identidad' }}
                        </Button>
                    </div>

                    <nav class="space-y-1">
                        <Link
                            v-for="item in personalItems"
                            :key="item.href"
                            :href="item.href"
                            :class="
                                cn(
                                    'flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold transition-colors',
                                    currentPath === item.href
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-secondary hover:bg-accent/60 hover:text-primary',
                                )
                            "
                            @click="mobileMenuOpen = false"
                        >
                            <component :is="item.icon" class="h-4 w-4 shrink-0" />
                            <span>{{ item.label }}</span>
                        </Link>

                        <template v-if="currentAgency && agencyItems.length">
                            <div class="my-2 border-t border-border/60 pt-2">
                                <p class="px-4 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                    {{ currentAgency.name }}
                                </p>
                            </div>
                            <Link
                                v-for="item in agencyItems"
                                :key="item.href"
                                :href="item.href"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold transition-colors',
                                        currentPath === item.href
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-secondary hover:bg-accent/60 hover:text-primary',
                                    )
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </template>

                        <div class="my-2 border-t border-border/60 pt-2">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-2xl px-4 py-2.5 text-sm font-semibold text-muted-foreground transition hover:bg-accent hover:text-destructive"
                                @click="logout"
                            >
                                <LogOut class="h-4 w-4 shrink-0" />
                                <span>Cerrar sesión</span>
                            </button>
                        </div>
                    </nav>
                </div>
            </aside>
        </Transition>

        <Toaster position="top-center" rich-colors />
    </div>
</template>