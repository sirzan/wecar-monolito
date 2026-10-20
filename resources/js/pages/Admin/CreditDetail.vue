<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    application: {
        id: string;
        folio: string;
        status: string;
        notes: string | null;
        full_name: string;
        email: string;
        phone: string;
        birthdate: string | null;
        rfc: string | null;
        curp: string | null;
        address: { calle: string | null; colonia: string | null; cp: string | null; alcaldia: string | null; ciudad: string | null; pais: string | null };
        employment: { ocupacion: string | null; empresa: string | null; antiguedad: string | null; ingreso_mensual: number; deuda_mensual: number; banco: string | null; tipo_cuenta: string | null };
        vehicle: { brand: string | null; model: string | null; version: string | null; year: number | null; price: number; km: number | null };
        credit: { financiera: string | null; score: number | null; risk_level: string | null; down_payment: number; term_months: number | null; monthly_payment: number; rate: number; cat: number; total_to_pay: number };
        documents: { slot: string; name: string | null; url: string }[];
        created_at: string | null;
        submitted_at: string | null;
    };
    statuses: string[];
    assignees: { id: string; name: string }[];
}>();

const form = useForm({
    status: props.application.status,
    notes: props.application.notes ?? '',
    assigned_to: '',
});

const submit = () => form.patch(`/admin/creditos/${props.application.id}`, { preserveScroll: true });

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <div class="mx-auto max-w-[1200px]">
        <Link href="/admin/creditos" class="inline-flex items-center gap-1 text-sm font-semibold text-muted-foreground hover:text-primary">
            <ArrowLeft class="h-4 w-4" /> Volver a créditos
        </Link>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="font-mono text-xs text-primary">{{ application.folio }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ application.full_name }}</h1>
                <p class="text-sm text-muted-foreground">{{ application.email }} · {{ application.phone }}</p>
            </div>
            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold uppercase text-primary">{{ application.status }}</span>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                <section class="rounded-2xl border border-border bg-card/50 p-5">
                    <h2 class="font-semibold">Datos personales</h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-muted-foreground">Nacimiento</dt><dd>{{ application.birthdate ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">RFC</dt><dd>{{ application.rfc ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">CURP</dt><dd>{{ application.curp ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-muted-foreground">Domicilio</dt><dd>{{ [application.address.calle, application.address.colonia, application.address.cp, application.address.alcaldia, application.address.ciudad, application.address.pais].filter(Boolean).join(', ') }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-border bg-card/50 p-5">
                    <h2 class="font-semibold">Información laboral</h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-muted-foreground">Ocupación</dt><dd>{{ application.employment.ocupacion ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Empresa</dt><dd>{{ application.employment.empresa ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Antigüedad</dt><dd>{{ application.employment.antiguedad ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Ingreso mensual</dt><dd>{{ formatPrice(application.employment.ingreso_mensual) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Deuda mensual</dt><dd>{{ formatPrice(application.employment.deuda_mensual) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Banco / cuenta</dt><dd>{{ application.employment.banco ?? '—' }} {{ application.employment.tipo_cuenta }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-border bg-card/50 p-5">
                    <h2 class="font-semibold">Auto y crédito</h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-muted-foreground">Auto</dt><dd>{{ application.vehicle.brand }} {{ application.vehicle.model }} {{ application.vehicle.version }} {{ application.vehicle.year }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Precio</dt><dd>{{ formatPrice(application.vehicle.price) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Financiera</dt><dd>{{ application.credit.financiera ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Score / riesgo</dt><dd>{{ application.credit.score ?? '—' }} / {{ application.credit.risk_level ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Enganche</dt><dd>{{ formatPrice(application.credit.down_payment) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Plazo</dt><dd>{{ application.credit.term_months ?? '—' }} meses</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Mensualidad</dt><dd>{{ formatPrice(application.credit.monthly_payment) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Tasa / CAT</dt><dd>{{ application.credit.rate }}% / {{ application.credit.cat }}%</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Total a pagar</dt><dd>{{ formatPrice(application.credit.total_to_pay) }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-border bg-card/50 p-5">
                    <h2 class="font-semibold">Documentos</h2>
                    <div class="mt-4 space-y-2">
                        <a v-for="document in application.documents" :key="document.slot" :href="document.url" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 rounded-xl border border-border p-3 text-sm hover:border-primary/40">
                            <FileText class="h-4 w-4 text-primary" />
                            <span class="font-semibold capitalize">{{ document.slot }}</span>
                            <span class="ml-auto text-xs text-muted-foreground">{{ document.name }}</span>
                        </a>
                        <p v-if="!application.documents.length" class="text-sm text-muted-foreground">Sin documentos.</p>
                    </div>
                </section>
            </div>

            <aside class="h-fit rounded-2xl border border-border bg-card/50 p-5">
                <h2 class="font-semibold">Gestión</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Estatus</label>
                        <select v-model="form.status" class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Asignar a</label>
                        <select v-model="form.assigned_to" class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option value="">Sin asignar</option>
                            <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">{{ assignee.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notas internas</label>
                        <textarea v-model="form.notes" rows="5" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="form.processing">Guardar cambios</Button>
                </form>
            </aside>
        </div>
    </div>
</template>
