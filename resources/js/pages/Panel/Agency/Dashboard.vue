<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, Car, Inbox, Users } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    agency: { id: string; slug: string; name: string; logo: string | null; plan: string; max_cars: number; city: string | null; role?: string };
    stats: { cars: number; active_cars: number; leads: number; team: number };
    recentCars: { id: string; slug: string; title: string; price: number; status?: string | null }[];
    recentLeads: { id: string; contact_name: string | null; contact_phone: string | null; status: string; created_at: string | null }[];
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
                <h1 class="text-2xl font-black text-secondary sm:text-3xl">{{ agency.name }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ agency.city ?? 'Sin ciudad' }} · Plan {{ agency.plan }}</p>
            </div>
            <Button as="a" href="/vende">Publicar auto</Button>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Car class="h-5 w-5 text-primary" />
                <p class="mt-3 text-2xl font-black text-secondary">{{ stats.cars }}</p>
                <p class="text-xs text-muted-foreground">Autos en inventario</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Building2 class="h-5 w-5 text-success" />
                <p class="mt-3 text-2xl font-black text-secondary">{{ stats.active_cars }}</p>
                <p class="text-xs text-muted-foreground">En venta</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Inbox class="h-5 w-5 text-primary" />
                <p class="mt-3 text-2xl font-black text-secondary">{{ stats.leads }}</p>
                <p class="text-xs text-muted-foreground">Leads</p>
            </div>
            <div class="rounded-2xl border border-border bg-card p-5 shadow-soft">
                <Users class="h-5 w-5 text-primary" />
                <p class="mt-3 text-2xl font-black text-secondary">{{ stats.team }}</p>
                <p class="text-xs text-muted-foreground">Vendedores activos</p>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Button as="a" :href="`${base}/inventario`" variant="outline">Inventario</Button>
            <Button as="a" :href="`${base}/leads`" variant="outline">Leads</Button>
            <Button as="a" :href="`${base}/equipo`" variant="outline">Equipo</Button>
            <Button as="a" :href="`${base}/perfil`" variant="outline">Perfil público</Button>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                <h2 class="font-black text-secondary">Inventario reciente</h2>
                <div v-if="recentCars.length" class="mt-4 divide-y divide-border">
                    <Link v-for="car in recentCars" :key="car.id" :href="`/auto/${car.slug}`" class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-semibold text-secondary">{{ car.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ car.status ?? 'Sin estatus' }}</p>
                        </div>
                        <p class="font-bold text-primary">{{ formatPrice(car.price) }}</p>
                    </Link>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Sin autos en inventario.</p>
            </section>

            <section class="rounded-3xl border border-border bg-card p-6 shadow-soft">
                <h2 class="font-black text-secondary">Leads recientes</h2>
                <div v-if="recentLeads.length" class="mt-4 space-y-3">
                    <div v-for="lead in recentLeads" :key="lead.id" class="rounded-xl border border-border p-3">
                        <p class="text-sm font-bold text-secondary">{{ lead.contact_name ?? 'Interesado' }}</p>
                        <p class="text-xs text-muted-foreground">{{ lead.contact_phone ?? 'Sin teléfono' }}</p>
                        <span class="mt-1 inline-block rounded-full bg-accent px-2 py-0.5 text-[10px] font-bold uppercase text-accent-foreground">{{ lead.status }}</span>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Sin leads por ahora.</p>
            </section>
        </div>
    </div>
</template>
