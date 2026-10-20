<script setup lang="ts">
import { Building2, Car, Inbox, ShieldCheck, TrendingUp, Users } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    counts: {
        users: number;
        new_users: number;
        cars: number;
        active_cars: number;
        agencies: number;
        active_agencies: number;
        leads: number;
        reports_open: number;
    };
    series: {
        users: { date: string; count: number }[];
        cars: { date: string; count: number }[];
        leads: { date: string; count: number }[];
    };
    recentReports: { id: string; reason: string; status: string; car: string; created_at: string | null }[];
    recentLeads: { id: string; contact_name: string | null; status: string; car: string; created_at: string | null }[];
}>();

const cards = [
    { key: 'cars', label: 'Autos', icon: Car, hint: `${props.counts.active_cars} en venta` },
    { key: 'users', label: 'Usuarios', icon: Users, hint: `${props.counts.new_users} nuevos en 30 días` },
    { key: 'agencies', label: 'Agencias', icon: Building2, hint: `${props.counts.active_agencies} activas` },
    { key: 'leads', label: 'Leads', icon: Inbox, hint: 'Contactos recibidos' },
    { key: 'reports_open', label: 'Reportes abiertos', icon: ShieldCheck, hint: 'Requieren revisión' },
] as const;

const maxSeries = Math.max(
    ...[...props.series.users, ...props.series.cars, ...props.series.leads].map((point) => point.count),
    1,
);

const bars = (series: { date: string; count: number }[]) => series.slice(-14);
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.14em] text-primary">
            <span class="h-1 w-1 rounded-full bg-primary" /> Operación
        </div>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Dashboard</h1>
        <p class="mt-1.5 text-sm text-muted-foreground">Resumen general de la plataforma Wecar.</p>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
            <div v-for="card in cards" :key="card.key" class="rounded-2xl border border-border bg-card/50 p-5">
                <div class="flex items-center justify-between">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary">
                        <component :is="card.icon" class="h-5 w-5" stroke-width="1.75" />
                    </span>
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-5 text-2xl font-semibold tabular-nums">{{ counts[card.key] }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ card.label }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground/70">{{ card.hint }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-5 lg:grid-cols-3">
            <section v-for="(series, key) in series" :key="key" class="rounded-2xl border border-border bg-card/50 p-5">
                <h2 class="text-sm font-semibold capitalize">{{ key === 'users' ? 'Usuarios nuevos' : key === 'cars' ? 'Autos publicados' : 'Leads' }} · 30 días</h2>
                <div class="mt-5 flex h-28 items-end gap-1">
                    <div
                        v-for="point in bars(series)"
                        :key="point.date"
                        class="flex-1 rounded-t bg-primary/60"
                        :style="{ height: `${Math.max((point.count / maxSeries) * 100, 4)}%` }"
                        :title="`${point.date}: ${point.count}`"
                    />
                </div>
            </section>
        </div>

        <div class="mt-8 grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-border bg-card/50 p-5">
                <h2 class="font-semibold">Reportes recientes</h2>
                <div v-if="recentReports.length" class="mt-4 divide-y divide-border">
                    <div v-for="report in recentReports" :key="report.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ report.car || 'Auto' }}</p>
                            <p class="text-xs text-muted-foreground">{{ report.reason }}</p>
                        </div>
                        <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-400">{{ report.status }}</span>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Sin reportes.</p>
            </section>

            <section class="rounded-2xl border border-border bg-card/50 p-5">
                <h2 class="font-semibold">Leads recientes</h2>
                <div v-if="recentLeads.length" class="mt-4 divide-y divide-border">
                    <div v-for="lead in recentLeads" :key="lead.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ lead.contact_name ?? 'Interesado' }}</p>
                            <p class="text-xs text-muted-foreground">{{ lead.car }}</p>
                        </div>
                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase text-primary">{{ lead.status }}</span>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-muted-foreground">Sin leads.</p>
            </section>
        </div>
    </div>
</template>
