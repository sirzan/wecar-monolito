<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    Clock,
    Eye,
    Loader2,
    Search,
    UserX,
    X,
    XCircle,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';

defineOptions({ layout: AdminLayout });

interface AppointmentRow {
    id: string;
    folio: string;
    full_name: string;
    email: string;
    whatsapp: string;
    state: string;
    vehicle: string;
    brand: string;
    model: string;
    year: number;
    scheduled_date: string;
    scheduled_time: string;
    status: string;
    status_label: string;
    notes: string | null;
    total: number;
    currency: string;
    created_at: string | null;
}

const props = defineProps<{
    appointments: {
        data: AppointmentRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Record<string, string>;
    statuses: Record<string, string>;
    counts: {
        today: number;
        upcoming: number;
        pending: number;
        confirmed: number;
        completed: number;
        cancelled: number;
    };
}>();

const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const date = ref(props.filters.date ?? '');

const detail = ref<AppointmentRow | null>(null);
const rescheduleTarget = ref<AppointmentRow | null>(null);
const rescheduleDate = ref('');
const rescheduleTime = ref('');
const rescheduleSlots = ref<{ time: string; available: boolean; reason: string | null }[]>([]);
const rescheduleLoading = ref(false);
const rescheduleError = ref('');
const updatingId = ref<string | null>(null);

const applyFilters = () => {
    router.get(
        '/admin/certificaciones',
        { q: search.value || undefined, status: status.value || undefined, date: date.value || undefined },
        { preserveState: true, replace: true },
    );
};

const changeStatus = (appointment: AppointmentRow, nextStatus: string) => {
    updatingId.value = appointment.id;
    router.patch(
        `/admin/certificaciones/${appointment.id}`,
        { status: nextStatus },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
};

const loadSlots = async (month: string) => {
    if (!month) {
        return;
    }

    rescheduleLoading.value = true;
    rescheduleError.value = '';

    try {
        const response = await fetch(`/api/certificaciones/disponibilidad?month=${month}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'No pudimos consultar la disponibilidad.');
        }

        const day = (payload.days ?? []).find((item: { date: string }) => item.date === rescheduleDate.value);
        rescheduleSlots.value = day?.slots ?? [];
    } catch (error) {
        rescheduleSlots.value = [];
        rescheduleError.value = error instanceof Error ? error.message : 'No pudimos consultar la disponibilidad.';
    } finally {
        rescheduleLoading.value = false;
    }
};

const openReschedule = (appointment: AppointmentRow) => {
    rescheduleTarget.value = appointment;
    rescheduleDate.value = appointment.scheduled_date;
    rescheduleTime.value = appointment.scheduled_time;
    rescheduleError.value = '';
    void loadSlots(appointment.scheduled_date.slice(0, 7));
};

watch(rescheduleDate, (value) => {
    if (!rescheduleTarget.value || !value) {
        return;
    }

    rescheduleTime.value = '';
    void loadSlots(value.slice(0, 7));
});

const confirmReschedule = () => {
    if (!rescheduleTarget.value || !rescheduleDate.value || !rescheduleTime.value) {
        return;
    }

    updatingId.value = rescheduleTarget.value.id;
    router.patch(
        `/admin/certificaciones/${rescheduleTarget.value.id}`,
        { scheduled_date: rescheduleDate.value, scheduled_time: rescheduleTime.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rescheduleTarget.value = null;
            },
            onError: (errors) => {
                rescheduleError.value = errors.scheduled_time ?? errors.scheduled_date ?? 'No se pudo reprogramar.';
            },
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
};

const formatPrice = (value: number, currency: string) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency, maximumFractionDigits: 2 }).format(value);

const formatDate = (value: string) => {
    const [year, month, day] = value.split('-').map(Number);

    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
};

const statusTone = (value: string) => {
    const tones: Record<string, string> = {
        pending: 'bg-amber-500/10 text-amber-400',
        confirmed: 'bg-sky-500/10 text-sky-400',
        completed: 'bg-emerald-500/10 text-emerald-400',
        cancelled: 'bg-rose-500/10 text-rose-400',
        no_show: 'bg-slate-500/10 text-slate-300',
    };

    return tones[value] ?? 'bg-muted text-muted-foreground';
};

const summary = computed(() => [
    { label: 'Citas de hoy', value: props.counts.today, icon: CalendarCheck2, tone: 'text-primary' },
    { label: 'Próximas', value: props.counts.upcoming, icon: CalendarClock, tone: 'text-sky-400' },
    { label: 'Pendientes', value: props.counts.pending, icon: Clock, tone: 'text-amber-400' },
    { label: 'Confirmadas', value: props.counts.confirmed, icon: CheckCircle2, tone: 'text-emerald-400' },
    { label: 'Completadas', value: props.counts.completed, icon: CalendarDays, tone: 'text-teal-400' },
    { label: 'Canceladas', value: props.counts.cancelled, icon: XCircle, tone: 'text-rose-400' },
]);
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Certificaciones</h1>
        <p class="mt-1 text-sm text-muted-foreground">Citas de revisión mecánica y legal.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div v-for="item in summary" :key="item.label" class="rounded-2xl border border-border bg-card/50 p-5">
                <component :is="item.icon" class="h-5 w-5" :class="item.tone" />
                <p class="mt-3 text-2xl font-semibold">{{ item.value }}</p>
                <p class="text-xs text-muted-foreground">{{ item.label }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-card/50 p-4">
            <div class="relative min-w-[220px] flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    placeholder="Buscar folio, nombre, WhatsApp o correo…"
                    class="h-10 w-full rounded-xl border border-input bg-background pl-9 pr-3 text-sm"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select v-model="status" class="h-10 rounded-xl border border-input bg-background px-3 text-sm">
                <option value="">Todos los estatus</option>
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <input v-model="date" type="date" class="h-10 rounded-xl border border-input bg-background px-3 text-sm" />
            <button class="h-10 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground" @click="applyFilters">
                Filtrar
            </button>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[1100px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="p-4">Folio</th>
                        <th class="p-4">Fecha</th>
                        <th class="p-4">Hora</th>
                        <th class="p-4">Cliente</th>
                        <th class="p-4">WhatsApp</th>
                        <th class="p-4">Vehículo</th>
                        <th class="p-4">Estado</th>
                        <th class="p-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="appointment in appointments.data" :key="appointment.id">
                        <td class="p-4 font-mono text-xs">{{ appointment.folio }}</td>
                        <td class="p-4 whitespace-nowrap">{{ formatDate(appointment.scheduled_date) }}</td>
                        <td class="p-4 whitespace-nowrap font-semibold">{{ appointment.scheduled_time }}</td>
                        <td class="p-4">
                            <p class="font-medium">{{ appointment.full_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ appointment.state }}</p>
                        </td>
                        <td class="p-4 whitespace-nowrap">{{ appointment.whatsapp }}</td>
                        <td class="p-4">{{ appointment.vehicle }}</td>
                        <td class="p-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusTone(appointment.status)">
                                {{ appointment.status_label }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    class="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground hover:bg-accent"
                                    title="Ver detalle"
                                    @click="detail = appointment"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="appointment.status === 'pending'"
                                    type="button"
                                    class="rounded-lg border border-emerald-500/40 px-2 py-1 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/10"
                                    :disabled="updatingId === appointment.id"
                                    @click="changeStatus(appointment, 'confirmed')"
                                >
                                    Confirmar
                                </button>
                                <button
                                    v-if="appointment.status === 'confirmed'"
                                    type="button"
                                    class="rounded-lg border border-teal-500/40 px-2 py-1 text-xs font-semibold text-teal-400 hover:bg-teal-500/10"
                                    :disabled="updatingId === appointment.id"
                                    @click="changeStatus(appointment, 'completed')"
                                >
                                    Completar
                                </button>
                                <button
                                    v-if="['pending', 'confirmed'].includes(appointment.status)"
                                    type="button"
                                    class="rounded-lg border border-sky-500/40 px-2 py-1 text-xs font-semibold text-sky-400 hover:bg-sky-500/10"
                                    @click="openReschedule(appointment)"
                                >
                                    Reprogramar
                                </button>
                                <button
                                    v-if="appointment.status === 'confirmed'"
                                    type="button"
                                    class="grid h-8 w-8 place-items-center rounded-lg border border-border text-muted-foreground hover:bg-accent"
                                    title="Registrar no asistió"
                                    @click="changeStatus(appointment, 'no_show')"
                                >
                                    <UserX class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="['pending', 'confirmed'].includes(appointment.status)"
                                    type="button"
                                    class="rounded-lg border border-rose-500/40 px-2 py-1 text-xs font-semibold text-rose-400 hover:bg-rose-500/10"
                                    :disabled="updatingId === appointment.id"
                                    @click="changeStatus(appointment, 'cancelled')"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!appointments.data.length">
                        <td colspan="8" class="p-10 text-center text-sm text-muted-foreground">
                            No hay citas con los filtros seleccionados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :current-page="appointments.current_page"
            :last-page="appointments.last_page"
            base-path="/admin/certificaciones"
            :base-query="filters"
        />

        <!-- Detalle -->
        <Teleport to="body">
            <div
                v-if="detail"
                class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm sm:items-center"
                @click="detail = null"
            >
                <div class="my-6 w-full max-w-xl rounded-2xl border border-border bg-card p-6 shadow-elegant" @click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-primary">Cita</p>
                            <h2 class="mt-1 text-xl font-semibold">{{ detail.folio }}</h2>
                        </div>
                        <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-accent" @click="detail = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div><p class="text-xs text-muted-foreground">Cliente</p><p class="font-medium">{{ detail.full_name }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Estado</p><p class="font-medium">{{ detail.state }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Correo</p><p class="font-medium break-all">{{ detail.email }}</p></div>
                        <div><p class="text-xs text-muted-foreground">WhatsApp</p><p class="font-medium">{{ detail.whatsapp }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Vehículo</p><p class="font-medium">{{ detail.vehicle }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Fecha y hora</p><p class="font-medium">{{ formatDate(detail.scheduled_date) }} · {{ detail.scheduled_time }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Total</p><p class="font-medium">{{ formatPrice(detail.total, detail.currency) }} (IVA incluido)</p></div>
                        <div><p class="text-xs text-muted-foreground">Estatus</p><p class="font-medium">{{ detail.status_label }}</p></div>
                        <div class="sm:col-span-2"><p class="text-xs text-muted-foreground">Notas</p><p class="font-medium">{{ detail.notes || 'Sin notas' }}</p></div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Reprogramar -->
        <Teleport to="body">
            <div
                v-if="rescheduleTarget"
                class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm sm:items-center"
                @click="rescheduleTarget = null"
            >
                <div class="my-6 w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-elegant" @click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-primary">Reprogramar</p>
                            <h2 class="mt-1 text-lg font-semibold">{{ rescheduleTarget.folio }}</h2>
                        </div>
                        <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-accent" @click="rescheduleTarget = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Nueva fecha</label>
                            <input v-model="rescheduleDate" type="date" class="mt-1 h-10 w-full rounded-xl border border-input bg-background px-3 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Nueva hora</label>
                            <div v-if="rescheduleLoading" class="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 class="h-4 w-4 animate-spin" /> Consultando disponibilidad…
                            </div>
                            <div v-else-if="rescheduleSlots.length" class="mt-2 grid grid-cols-3 gap-2">
                                <button
                                    v-for="slot in rescheduleSlots"
                                    :key="slot.time"
                                    type="button"
                                    class="rounded-lg border px-2 py-2 text-xs font-semibold"
                                    :class="rescheduleTime === slot.time ? 'border-primary bg-primary text-primary-foreground' : slot.available ? 'border-border hover:border-primary/50' : 'cursor-not-allowed border-border text-muted-foreground/50'"
                                    :disabled="!slot.available"
                                    :title="slot.reason ?? undefined"
                                    @click="rescheduleTime = slot.time"
                                >
                                    {{ slot.time }}
                                </button>
                            </div>
                            <p v-else class="mt-2 text-xs text-muted-foreground">No hay horarios disponibles para esa fecha.</p>
                        </div>
                        <p v-if="rescheduleError" class="text-xs font-semibold text-rose-400">{{ rescheduleError }}</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="h-10 rounded-xl border border-border px-4 text-sm" @click="rescheduleTarget = null">
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            :disabled="!rescheduleDate || !rescheduleTime || updatingId === rescheduleTarget.id"
                            @click="confirmReschedule"
                        >
                            <Loader2 v-if="updatingId === rescheduleTarget.id" class="h-4 w-4 animate-spin" />
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
