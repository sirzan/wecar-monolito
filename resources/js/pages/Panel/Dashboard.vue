<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, Car, CreditCard, Inbox, Plus, ShieldCheck, Sparkles } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

defineProps<{
    stats: { cars: number; active_cars: number; agency_cars: number; leads: number };
    agencies: { id: string; slug: string; name: string; role: string | null; cars: number; plan: string }[];
    recentCars: { id: string; slug: string; title: string; price: number; status?: string | null }[];
    recentLeads: { id: string; contact_name: string | null; contact_phone: string | null; status: string; car: string; created_at: string | null }[];
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-black text-secondary sm:text-3xl">Panel de Control</h1>
            <p class="mt-1 text-sm text-muted-foreground">Administra tus publicaciones de vehículos, agencias y solicitudes de crédito.</p>
        </div>

        <!-- Banner de acceso rápido / Acción principal -->
        <div class="relative overflow-hidden rounded-3xl bg-secondary p-6 text-secondary-foreground shadow-lg sm:p-8">
            <div class="relative z-10 max-w-xl">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 text-xs font-bold text-primary">
                    <Sparkles class="h-3.5 w-3.5" /> Publicación ágil
                </span>
                <h2 class="mt-3 text-xl font-black sm:text-2xl">¿Listo para vender tu auto al mejor precio?</h2>
                <p class="mt-2 text-sm text-secondary-foreground/80">
                    Publica en menos de 2 minutos. Consulta el valor referencial de Libro Azul y recibe prospectos precalificados.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <Button as="a" href="/vende" variant="hero" class="gap-2">
                        <Plus class="h-4 w-4" /> Publicar auto
                    </Button>
                    <Button as="a" href="/panel/tramites" variant="outline" class="border-white/20 text-white hover:bg-white/10">
                        <CreditCard class="h-4 w-4" /> Mis trámites de crédito
                    </Button>
                </div>
            </div>
            <div class="pointer-events-none absolute -bottom-10 -right-10 h-64 w-64 rounded-full bg-primary/20 blur-3xl" />
        </div>

        <!-- Métricas numéricas -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-muted-foreground">Mis publicaciones</p>
                    <Car class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-3 text-3xl font-black text-secondary">{{ stats.cars }}</p>
                <Link href="/panel/publicaciones" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                    Ver publicaciones <ArrowRight class="h-3 w-3" />
                </Link>
            </div>

            <div class="rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-muted-foreground">Autos activos</p>
                    <ShieldCheck class="h-4 w-4 text-emerald-600" />
                </div>
                <p class="mt-3 text-3xl font-black text-secondary">{{ stats.active_cars }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Visibles en el catálogo público</p>
            </div>

            <div class="rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-muted-foreground">Autos de agencia</p>
                    <Building2 class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-3 text-3xl font-black text-secondary">{{ stats.agency_cars }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Inventario compartido de red</p>
            </div>

            <div class="rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-muted-foreground">Prospectos / Leads</p>
                    <Inbox class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-3 text-3xl font-black text-secondary">{{ stats.leads }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Interesados en tus autos</p>
            </div>
        </div>

        <!-- Secciones de Agencia y Leads -->
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-border/70 bg-card p-6 shadow-soft">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-black text-secondary">Mis agencias asociadas</h2>
                    <Link href="/panel/agencia/crear" class="text-xs font-bold text-primary hover:underline">Crear agencia</Link>
                </div>
                <div v-if="agencies.length" class="mt-4 space-y-3">
                    <Link
                        v-for="agency in agencies"
                        :key="agency.id"
                        :href="`/panel/agencia/${agency.slug}/resumen`"
                        class="flex items-center justify-between rounded-2xl border border-border p-4 transition hover:border-primary/50 hover:bg-muted/30"
                    >
                        <div>
                            <p class="font-bold text-secondary">{{ agency.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ agency.cars }} autos publicados · Plan {{ agency.plan }}</p>
                        </div>
                        <span class="rounded-full bg-accent px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-primary">
                            {{ agency.role }}
                        </span>
                    </Link>
                </div>
                <div v-else class="mt-4 rounded-2xl border border-dashed border-border p-6 text-center">
                    <p class="text-xs text-muted-foreground">¿Eres lote o concesionaria? Crea una agencia para inventario colaborativo.</p>
                    <Button as="a" href="/panel/agencia/crear" variant="outline" size="sm" class="mt-3">
                        Crear perfil de agencia
                    </Button>
                </div>
            </section>

            <section class="rounded-3xl border border-border/70 bg-card p-6 shadow-soft">
                <h2 class="text-base font-black text-secondary">Leads de compradores recientes</h2>
                <div v-if="recentLeads.length" class="mt-4 space-y-3">
                    <div v-for="lead in recentLeads" :key="lead.id" class="flex items-center justify-between rounded-2xl border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-secondary">{{ lead.contact_name ?? 'Comprador interesado' }}</p>
                            <p class="text-xs text-muted-foreground">{{ lead.car }} · {{ lead.contact_phone ?? 'Teléfono privado' }}</p>
                        </div>
                        <span class="rounded-full bg-accent px-2.5 py-0.5 text-[10px] font-bold uppercase text-primary">
                            {{ lead.status }}
                        </span>
                    </div>
                </div>
                <div v-else class="mt-4 rounded-2xl border border-dashed border-border p-6 text-center">
                    <p class="text-xs text-muted-foreground">Aún no tienes mensajes ni solicitudes de contacto.</p>
                </div>
            </section>
        </div>

        <!-- Publicaciones Recientes -->
        <section class="rounded-3xl border border-border/70 bg-card p-6 shadow-soft">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-black text-secondary">Publicaciones recientes</h2>
                <Link href="/panel/publicaciones" class="text-xs font-bold text-primary hover:underline">Ver todas</Link>
            </div>
            <div v-if="recentCars.length" class="mt-4 divide-y divide-border/60">
                <Link
                    v-for="car in recentCars"
                    :key="car.id"
                    :href="`/auto/${car.slug}`"
                    class="flex items-center justify-between py-3 transition hover:text-primary"
                >
                    <div>
                        <p class="font-bold text-secondary">{{ car.title }}</p>
                        <p class="text-xs text-muted-foreground capitalize">{{ car.status ?? 'activo' }}</p>
                    </div>
                    <p class="font-black text-primary">{{ formatPrice(car.price) }}</p>
                </Link>
            </div>
            <div v-else class="mt-4 rounded-2xl border border-dashed border-border p-6 text-center">
                <p class="text-xs text-muted-foreground">Aún no has publicado ningún vehículo.</p>
                <Button as="a" href="/vende" variant="outline" size="sm" class="mt-3">
                    Publicar ahora
                </Button>
            </div>
        </section>
    </div>
</template>
