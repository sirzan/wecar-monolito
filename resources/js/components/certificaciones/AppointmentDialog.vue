<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Loader2, MapPin, X } from '@lucide/vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { cn } from '@/lib/utils';

interface Pricing {
    base_price: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    currency: string;
    label: string;
}

interface Workshop {
    name: string;
    address: string;
    note: string;
}

interface CertificationInfo {
    service: string;
    points: number;
    timezone: string;
    duration_minutes: number;
    min_notice_hours: number;
    capacity_per_slot: number;
    pricing: Pricing;
    workshop: Workshop;
    schedule_configured: boolean;
}

interface Brand {
    id: string;
    name: string;
    models: { id: string; name: string }[];
}

interface Profile {
    full_name: string;
    email: string;
    whatsapp: string;
    state: string;
}

interface Slot {
    time: string;
    available: boolean;
    remaining: number;
    reason: string | null;
}

interface DayAvailability {
    date: string;
    available: boolean;
    slots: Slot[];
}

interface Appointment {
    folio: string;
    full_name: string;
    service: string;
    scheduled_date: string;
    scheduled_time: string;
    timezone: string;
    duration_minutes: number;
    vehicle: string;
    base_price: number;
    tax_amount: number;
    total: number;
    currency: string;
    status: string;
    status_label: string;
    workshop: Workshop;
}

const props = defineProps<{
    open: boolean;
    certification: CertificationInfo;
    brands: Brand[];
    profile: Profile | null;
}>();

const emit = defineEmits<{ close: [] }>();

const timezone = computed(() => props.certification.timezone || 'America/Monterrey');

const todayInTimezone = (): string =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());

const form = reactive({
    full_name: '',
    email: '',
    whatsapp: '',
    state: '',
    brand: '',
    model: '',
    year: '',
    notes: '',
});

const currentMonth = ref(todayInTimezone().slice(0, 7));
const availability = ref<DayAvailability[]>([]);
const loadingAvailability = ref(false);
const availabilityError = ref('');
const selectedDate = ref('');
const selectedTime = ref('');
const submitting = ref(false);
const submitError = ref('');
const fieldErrors = ref<Record<string, string[]>>({});
const success = ref<Appointment | null>(null);

const priceFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: props.certification.pricing.currency || 'MXN',
    maximumFractionDigits: 0,
});

const formatPrice = (value: number) => priceFormatter.format(value);

const monthLabel = computed(() => {
    const [year, month] = currentMonth.value.split('-').map(Number);

    return new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(Date.UTC(year, month - 1, 1)),
    );
});

const monthCells = computed(() => {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const totalDays = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const firstDay = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();
    const leading = (firstDay + 6) % 7;
    const today = todayInTimezone();

    const cells: Array<{ date: string; day: number; available: boolean; isPast: boolean } | null> = Array(
        leading,
    ).fill(null);

    for (let day = 1; day <= totalDays; day += 1) {
        const date = `${currentMonth.value}-${String(day).padStart(2, '0')}`;
        const info = availability.value.find((item) => item.date === date);

        cells.push({
            date,
            day,
            available: Boolean(info?.available),
            isPast: date < today,
        });
    }

    return cells;
});

const selectedDay = computed(() => availability.value.find((item) => item.date === selectedDate.value) ?? null);
const slots = computed<Slot[]>(() => selectedDay.value?.slots ?? []);

const modelOptions = computed(() => {
    const brand = props.brands.find((item) => item.name === form.brand);

    return brand?.models ?? [];
});

const canGoPrevious = computed(() => currentMonth.value > todayInTimezone().slice(0, 7));

const canSubmit = computed(
    () =>
        form.full_name.trim().length >= 2 &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email) &&
        form.whatsapp.trim().length >= 10 &&
        form.state.trim().length >= 2 &&
        selectedDate.value !== '' &&
        selectedTime.value !== '' &&
        form.brand !== '' &&
        form.model !== '' &&
        form.year !== '',
);

const formatLongDate = (date: string) => {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
};

const loadAvailability = async () => {
    loadingAvailability.value = true;
    availabilityError.value = '';

    try {
        const response = await fetch(`/api/certificaciones/disponibilidad?month=${currentMonth.value}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'No pudimos consultar la disponibilidad.');
        }

        availability.value = payload.days ?? [];

        if (selectedDate.value && !availability.value.some((day) => day.date === selectedDate.value && day.available)) {
            selectedDate.value = '';
            selectedTime.value = '';
        }
    } catch (error) {
        availability.value = [];
        availabilityError.value = error instanceof Error ? error.message : 'No pudimos consultar la disponibilidad.';
    } finally {
        loadingAvailability.value = false;
    }
};

const prefilled = ref(false);

const prefill = () => {
    if (prefilled.value || !props.profile) {
        return;
    }

    form.full_name = props.profile.full_name ?? '';
    form.email = props.profile.email ?? '';
    form.whatsapp = props.profile.whatsapp ?? '';
    form.state = props.profile.state ?? '';
    prefilled.value = true;
};

const reset = () => {
    form.full_name = '';
    form.email = '';
    form.whatsapp = '';
    form.state = '';
    form.brand = '';
    form.model = '';
    form.year = '';
    form.notes = '';
    selectedDate.value = '';
    selectedTime.value = '';
    submitError.value = '';
    fieldErrors.value = {};
    success.value = null;
    currentMonth.value = todayInTimezone().slice(0, 7);
    availability.value = [];
    prefilled.value = false;
};

watch(
    () => props.open,
    (open) => {
        if (open) {
            if (success.value) {
                reset();
            }
            prefill();
            void loadAvailability();
        }
    },
);

watch(currentMonth, () => {
    if (props.open) {
        void loadAvailability();
    }
});

const changeMonth = (offset: number) => {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const next = new Date(Date.UTC(year, month - 1 + offset, 1));
    const value = `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;

    if (value < todayInTimezone().slice(0, 7)) {
        return;
    }

    currentMonth.value = value;
    selectedDate.value = '';
    selectedTime.value = '';
};

const selectDate = (cell: { date: string; available: boolean }) => {
    if (!cell.available) {
        return;
    }

    selectedDate.value = cell.date;
    selectedTime.value = '';
};

const submit = async () => {
    if (!canSubmit.value || submitting.value) {
        return;
    }

    submitting.value = true;
    submitError.value = '';
    fieldErrors.value = {};

    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch('/api/certificaciones/citas', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                full_name: form.full_name,
                email: form.email,
                whatsapp: form.whatsapp,
                state: form.state,
                scheduled_date: selectedDate.value,
                scheduled_time: selectedTime.value,
                brand: form.brand,
                model: form.model,
                year: Number(form.year),
                notes: form.notes || null,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (payload.errors) {
                fieldErrors.value = payload.errors;
            }

            throw new Error(payload.message || 'No pudimos registrar tu cita. Intenta de nuevo.');
        }

        success.value = payload.appointment as Appointment;
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'No pudimos registrar tu cita. Intenta de nuevo.';
        await loadAvailability();
    } finally {
        submitting.value = false;
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

watch(
    () => props.open,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    },
    { immediate: true },
);

const errorFor = (field: string): string | null => fieldErrors.value[field]?.[0] ?? null;
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="certification-dialog-title"
            @click="emit('close')"
        >
            <div
                class="relative my-6 w-full max-w-[720px] rounded-[28px] border border-border bg-card shadow-elegant"
                @click.stop
            >
                <button
                    type="button"
                    aria-label="Cerrar"
                    class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-secondary"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="border-b border-border px-5 py-5 pr-14 sm:px-7">
                    <h2 id="certification-dialog-title" class="text-xl font-black text-secondary sm:text-2xl">
                        {{ success ? 'Solicitud de cita registrada' : 'Agenda tu revisión' }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ certification.service }} — {{ certification.pricing.label }}. Selecciona fecha y hora.
                    </p>
                </div>

                <!-- Confirmación -->
                <div v-if="success" class="space-y-5 px-5 py-6 sm:px-7">
                    <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <CheckCircle2 class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" />
                        <div>
                            <p class="font-bold text-emerald-800">Tu solicitud quedó registrada.</p>
                            <p class="mt-1 text-sm text-emerald-900/80">
                                La cita queda pendiente de confirmación por el taller. Te contactaremos por correo o
                                WhatsApp.
                            </p>
                        </div>
                    </div>

                    <dl class="grid gap-3 rounded-2xl border border-border bg-muted/30 p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Folio</dt>
                            <dd class="mt-0.5 font-bold text-secondary">{{ success.folio }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Cliente</dt>
                            <dd class="mt-0.5 font-semibold text-secondary">{{ success.full_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Servicio</dt>
                            <dd class="mt-0.5 text-secondary">{{ success.service }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Vehículo</dt>
                            <dd class="mt-0.5 text-secondary">{{ success.vehicle }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Fecha y hora</dt>
                            <dd class="mt-0.5 text-secondary">
                                {{ formatLongDate(success.scheduled_date) }} · {{ success.scheduled_time }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Precio</dt>
                            <dd class="mt-0.5 text-secondary">
                                {{ formatPrice(success.base_price) }} {{ success.currency }} + IVA
                                <span class="text-xs text-muted-foreground">({{ formatPrice(success.total) }} total)</span>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Ubicación</dt>
                            <dd class="mt-0.5 flex items-start gap-2 text-secondary">
                                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>
                                    <strong>{{ success.workshop.name }}</strong><br />
                                    {{ success.workshop.address }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <Button class="w-full rounded-full" @click="emit('close')">Cerrar</Button>
                </div>

                <!-- Formulario -->
                <form v-else class="space-y-5 px-5 py-6 sm:px-7" @submit.prevent="submit">
                    <div>
                        <Label for="cert-name" class="text-xs font-bold text-secondary">Nombre completo *</Label>
                        <Input id="cert-name" v-model="form.full_name" placeholder="Nombre y apellidos" class="mt-1.5 h-11 rounded-full bg-[#FAF9F7]" />
                        <p v-if="errorFor('full_name')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('full_name') }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="cert-email" class="text-xs font-bold text-secondary">Correo electrónico *</Label>
                            <Input id="cert-email" v-model="form.email" type="email" placeholder="tu@correo.com" class="mt-1.5 h-11 rounded-full bg-[#FAF9F7]" />
                            <p v-if="errorFor('email')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('email') }}</p>
                        </div>
                        <div>
                            <Label for="cert-whatsapp" class="text-xs font-bold text-secondary">WhatsApp *</Label>
                            <Input id="cert-whatsapp" v-model="form.whatsapp" type="tel" placeholder="81 1234 5678" class="mt-1.5 h-11 rounded-full bg-[#FAF9F7]" />
                            <p v-if="errorFor('whatsapp')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('whatsapp') }}</p>
                        </div>
                    </div>

                    <div>
                        <Label for="cert-state" class="text-xs font-bold text-secondary">Estado *</Label>
                        <Input id="cert-state" v-model="form.state" placeholder="Ej. Nuevo León" class="mt-1.5 h-11 rounded-full bg-[#FAF9F7]" />
                        <p v-if="errorFor('state')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('state') }}</p>
                    </div>

                    <!-- Fecha y hora -->
                    <div class="grid gap-4 sm:grid-cols-[1fr_220px]">
                        <div class="rounded-2xl border border-border bg-[#FAF9F7] p-4">
                            <div class="flex items-center justify-between">
                                <button
                                    type="button"
                                    class="grid h-8 w-8 place-items-center rounded-full text-secondary transition hover:bg-muted disabled:opacity-40"
                                    :disabled="!canGoPrevious"
                                    aria-label="Mes anterior"
                                    @click="changeMonth(-1)"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </button>
                                <span class="text-sm font-bold capitalize text-secondary">{{ monthLabel }}</span>
                                <button
                                    type="button"
                                    class="grid h-8 w-8 place-items-center rounded-full text-secondary transition hover:bg-muted"
                                    aria-label="Mes siguiente"
                                    @click="changeMonth(1)"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-muted-foreground">
                                <span v-for="label in ['L', 'M', 'X', 'J', 'V', 'S', 'D']" :key="label">{{ label }}</span>
                            </div>

                            <div class="mt-1 grid grid-cols-7 gap-1">
                                <span v-for="(cell, index) in monthCells" :key="index">
                                    <button
                                        v-if="cell"
                                        type="button"
                                        class="grid h-8 w-full place-items-center rounded-full text-xs font-semibold transition"
                                        :class="cn(
                                            selectedDate === cell.date
                                                ? 'bg-primary text-primary-foreground shadow-soft'
                                                : cell.available
                                                ? 'text-secondary hover:bg-primary/10'
                                                : 'cursor-not-allowed text-muted-foreground/40',
                                        )"
                                        :disabled="!cell.available"
                                        @click="selectDate(cell)"
                                    >
                                        {{ cell.day }}
                                    </button>
                                </span>
                            </div>

                            <p v-if="loadingAvailability" class="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
                                <Loader2 class="h-3.5 w-3.5 animate-spin" /> Consultando disponibilidad…
                            </p>
                            <p v-else-if="availabilityError" class="mt-3 text-[11px] font-semibold text-destructive">{{ availabilityError }}</p>
                            <p v-else-if="!certification.schedule_configured" class="mt-3 text-[11px] text-muted-foreground">
                                La agenda en línea aún no está configurada. Contáctanos para agendar.
                            </p>
                            <p v-else class="mt-3 text-[11px] text-muted-foreground">Selecciona un día disponible en el calendario.</p>
                        </div>

                        <div class="rounded-2xl border border-border bg-[#FAF9F7] p-4">
                            <p class="flex items-center gap-2 text-xs font-bold text-secondary">
                                <CalendarDays class="h-4 w-4 text-primary" />
                                {{ selectedDate ? formatLongDate(selectedDate) : 'Hora de revisión' }}
                            </p>

                            <div v-if="selectedDate && slots.length" class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    v-for="slot in slots"
                                    :key="slot.time"
                                    type="button"
                                    class="rounded-full border px-2 py-2 text-xs font-semibold transition"
                                    :class="cn(
                                        selectedTime === slot.time
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : slot.available
                                            ? 'border-border bg-white text-secondary hover:border-primary/50'
                                            : 'cursor-not-allowed border-border bg-muted text-muted-foreground/50',
                                    )"
                                    :disabled="!slot.available"
                                    :title="slot.reason ?? undefined"
                                    @click="selectedTime = slot.time"
                                >
                                    {{ slot.time }}
                                </button>
                            </div>
                            <p v-else class="mt-3 text-xs text-muted-foreground">
                                {{ selectedDate ? 'No hay horarios disponibles para este día.' : 'Elige una fecha para ver los horarios.' }}
                            </p>
                            <p v-if="errorFor('scheduled_time')" class="mt-2 text-xs font-semibold text-destructive">{{ errorFor('scheduled_time') }}</p>
                        </div>
                    </div>
                    <p v-if="errorFor('scheduled_date')" class="text-xs font-semibold text-destructive">{{ errorFor('scheduled_date') }}</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label for="cert-brand" class="text-xs font-bold text-secondary">Marca *</Label>
                            <select
                                id="cert-brand"
                                v-model="form.brand"
                                class="mt-1.5 h-11 w-full rounded-full border border-input bg-[#FAF9F7] px-4 text-sm text-secondary outline-none focus:border-primary"
                                @change="form.model = ''"
                            >
                                <option value="">Marca</option>
                                <option v-for="brand in brands" :key="brand.id" :value="brand.name">{{ brand.name }}</option>
                            </select>
                            <p v-if="errorFor('brand')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('brand') }}</p>
                        </div>
                        <div>
                            <Label for="cert-model" class="text-xs font-bold text-secondary">Modelo *</Label>
                            <select
                                id="cert-model"
                                v-model="form.model"
                                :disabled="!form.brand"
                                class="mt-1.5 h-11 w-full rounded-full border border-input bg-[#FAF9F7] px-4 text-sm text-secondary outline-none focus:border-primary disabled:opacity-50"
                            >
                                <option value="">{{ form.brand ? 'Modelo' : 'Marca primero' }}</option>
                                <option v-for="model in modelOptions" :key="model.id" :value="model.name">{{ model.name }}</option>
                            </select>
                            <p v-if="errorFor('model')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('model') }}</p>
                        </div>
                        <div>
                            <Label for="cert-year" class="text-xs font-bold text-secondary">Año *</Label>
                            <Input id="cert-year" v-model="form.year" type="number" min="1990" :max="new Date().getFullYear() + 1" placeholder="2022" class="mt-1.5 h-11 rounded-full bg-[#FAF9F7]" />
                            <p v-if="errorFor('year')" class="mt-1 text-xs font-semibold text-destructive">{{ errorFor('year') }}</p>
                        </div>
                    </div>

                    <div>
                        <Label for="cert-notes" class="text-xs font-bold text-secondary">Notas adicionales</Label>
                        <textarea
                            id="cert-notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Detalles que el taller deba conocer."
                            class="mt-1.5 w-full rounded-[22px] border border-input bg-[#FAF9F7] p-4 text-sm text-secondary outline-none focus:border-primary"
                        />
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl border border-border bg-muted/40 p-4">
                        <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                        <div class="text-sm">
                            <p class="font-bold text-secondary">{{ certification.workshop.name }}</p>
                            <p class="text-muted-foreground">{{ certification.workshop.address }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ certification.workshop.note }}</p>
                        </div>
                    </div>

                    <p v-if="submitError" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm font-semibold text-destructive">
                        {{ submitError }}
                    </p>

                    <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted-foreground">
                            {{ certification.pricing.label }} · Cita pendiente de confirmación.
                        </p>
                        <Button type="submit" variant="hero" size="lg" class="w-full rounded-full shadow-glow sm:w-auto" :disabled="!canSubmit || submitting">
                            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                            <CalendarDays v-else class="h-4 w-4" />
                            {{ submitting ? 'Registrando…' : 'Confirmar cita' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
