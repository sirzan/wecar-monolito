<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    FileCheck,
    FileText,
    HelpCircle,
    Loader2,
    Plus,
    Printer,
    ShieldAlert,
    ShieldCheck,
    Upload,
    X,
    XCircle,
} from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import { toast } from 'vue-sonner';

defineOptions({ layout: PanelLayout });

interface Application {
    id: string;
    folio: string;
    status: string;
    car: string;
    financiera: string | null;
    score: number | null;
    risk_level: string | null;
    down_payment: number;
    term_months: number | null;
    monthly_payment: number;
    rate?: number | null;
    cat?: number | null;
    total_to_pay?: number | null;
    created_at: string | null;
    documents: { slot: string; name: string | null; url: string }[];
}

const props = defineProps<{
    applications: { data: Application[]; current_page: number; last_page: number };
    counts: { total: number; review: number; approved: number };
}>();

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const statusMeta = (status: string) => {
    if (status === 'aprobado') {
        return { label: 'Aprobado', icon: CheckCircle2, className: 'bg-emerald-500/10 text-emerald-600 border-emerald-300' };
    }
    if (status === 'rechazado') {
        return { label: 'No aprobado', icon: XCircle, className: 'bg-rose-500/10 text-rose-600 border-rose-300' };
    }
    return { label: 'En revisión', icon: Clock, className: 'bg-amber-500/10 text-amber-600 border-amber-300' };
};

// Documentos globales del usuario
interface SavedDoc {
    slot: string;
    label: string;
    saved: boolean;
    name: string | null;
    url: string | null;
}

const docSlots = [
    { slot: 'ine', label: 'INE / Identificación oficial' },
    { slot: 'domicilio', label: 'Comprobante de domicilio' },
    { slot: 'estado_cuenta', label: 'Estados de cuenta / Ingresos' },
];

const savedDocs = ref<Record<string, { saved: boolean; name: string | null; url: string | null }>>({
    ine: { saved: false, name: null, url: null },
    domicilio: { saved: false, name: null, url: null },
    estado_cuenta: { saved: false, name: null, url: null },
});
const uploadingSlot = ref<string | null>(null);

const loadUserDocuments = async () => {
    try {
        const res = await fetch('/api/credito/documentos');
        if (res.ok) {
            const data = await res.json();
            const savedList = data.saved || [];
            const urls = data.urls || {};
            for (const item of docSlots) {
                const isSaved = savedList.includes(item.slot);
                savedDocs.value[item.slot] = {
                    saved: isSaved,
                    name: isSaved ? `${item.label}.pdf` : null,
                    url: urls[item.slot] || null,
                };
            }
        }
    } catch {
        // Silencioso si no hay sesión iniciada
    }
};

const handleUploadDoc = async (slot: string, e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file) return;

    if (file.type !== 'application/pdf') {
        toast.error('Solo se admiten documentos en formato PDF');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        toast.error('El archivo no debe exceder 5 MB');
        return;
    }

    try {
        uploadingSlot.value = slot;
        const formData = new FormData();
        formData.append('file', file);
        formData.append('slot', slot);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/credito/documentos', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (res.ok) {
            toast.success('Documento guardado en tu cuenta.');
            await loadUserDocuments();
        } else {
            const err = await res.json().catch(() => ({}));
            toast.error(err.message || 'Error al subir el documento.');
        }
    } catch {
        toast.error('Error de conexión al subir documento.');
    } finally {
        uploadingSlot.value = null;
    }
};

// Cotización modal
const selectedApp = ref<Application | null>(null);
const printQuote = () => {
    window.print();
};

onMounted(() => {
    loadUserDocuments();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-secondary sm:text-3xl">Mis trámites de crédito</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sigue en tiempo real el estatus de tu financiamiento y administra tu expediente.
                </p>
            </div>
            <Button as="a" href="/credito" variant="hero" class="shrink-0 gap-2">
                <Plus class="h-4 w-4" /> Nueva solicitud
            </Button>
        </div>

        <!-- Métricas Rápidas -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-4 rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <CreditCard class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-2xl font-black text-secondary">{{ counts.total }}</p>
                    <p class="text-xs text-muted-foreground">Solicitudes registradas</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-500/10 text-amber-600">
                    <Clock class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-2xl font-black text-secondary">{{ counts.review }}</p>
                    <p class="text-xs text-muted-foreground">En evaluación bancaria</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-3xl border border-border/70 bg-card p-5 shadow-soft">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600">
                    <CheckCircle2 class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-2xl font-black text-secondary">{{ counts.approved }}</p>
                    <p class="text-xs text-muted-foreground">Aprobadas</p>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Expediente Digital de Documentos -->
        <div class="overflow-hidden rounded-3xl border border-border/70 bg-card p-6 shadow-soft">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-base font-black text-secondary">Mi Expediente Digital</h2>
                    <p class="text-xs text-muted-foreground">
                        Estos documentos se guardan de forma encriptada y aceleran tus solicitudes de crédito. Solo PDF (máx. 5 MB).
                    </p>
                </div>
                <Badge variant="outline" size="sm" class="w-fit text-[11px]">
                    Reutilizable en todas las solicitudes
                </Badge>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div
                    v-for="slot in docSlots"
                    :key="slot.slot"
                    class="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-background/50 p-4 transition hover:border-primary/50"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <CheckCircle2
                                v-if="savedDocs[slot.slot]?.saved"
                                class="h-4 w-4 shrink-0 text-emerald-600"
                            />
                            <FileText v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="text-xs font-bold text-secondary">{{ slot.label }}</span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-[11px] text-muted-foreground">
                            {{
                                uploadingSlot === slot.slot
                                    ? 'Subiendo...'
                                    : savedDocs[slot.slot]?.saved
                                      ? 'Guardado en tu cuenta'
                                      : 'Pendiente de subir'
                            }}
                        </span>

                        <label
                            class="inline-flex cursor-pointer items-center gap-1 text-xs font-bold text-primary hover:underline"
                        >
                            <Loader2 v-if="uploadingSlot === slot.slot" class="h-3.5 w-3.5 animate-spin" />
                            <Upload v-else class="h-3.5 w-3.5" />
                            <span>{{ savedDocs[slot.slot]?.saved ? 'Remplazar' : 'Subir' }}</span>
                            <input
                                type="file"
                                accept="application/pdf"
                                class="hidden"
                                :disabled="uploadingSlot === slot.slot"
                                @change="handleUploadDoc(slot.slot, $event)"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Listado de Solicitudes -->
        <div v-if="applications.data.length" class="space-y-4">
            <article
                v-for="app in applications.data"
                :key="app.id"
                class="rounded-3xl border border-border/70 bg-card p-6 shadow-soft transition hover:shadow-md"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-primary">{{ app.folio }}</span>
                            <span class="text-xs text-muted-foreground">· {{ formatDate(app.created_at) }}</span>
                        </div>
                        <h2 class="mt-1 text-lg font-black text-secondary">{{ app.car }}</h2>
                        <p class="text-xs font-semibold text-muted-foreground">
                            Financiera: <span class="text-secondary">{{ app.financiera || 'Por asignar por analista' }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold"
                            :class="statusMeta(app.status).className"
                        >
                            <component :is="statusMeta(app.status).icon" class="h-3.5 w-3.5" />
                            {{ statusMeta(app.status).label }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-1 text-xs"
                            @click="selectedApp = app"
                        >
                            <FileText class="h-3.5 w-3.5" /> Ver cotización
                        </Button>
                    </div>
                </div>

                <!-- Timeline de Etapas de la Solicitud -->
                <div class="mt-5 border-y border-border/60 py-4">
                    <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        Etapas del financiamiento
                    </p>
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div class="flex flex-col items-center">
                            <div class="grid h-7 w-7 place-items-center rounded-full bg-emerald-500 text-white shadow-xs">
                                <Check class="h-3.5 w-3.5" />
                            </div>
                            <span class="mt-1 font-semibold text-secondary">1. Solicitud</span>
                            <span class="text-[10px] text-emerald-600">Recibida</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div
                                class="grid h-7 w-7 place-items-center rounded-full text-white shadow-xs"
                                :class="app.score ? 'bg-emerald-500' : 'bg-primary'"
                            >
                                <Check v-if="app.score" class="h-3.5 w-3.5" />
                                <span v-else class="text-xs font-bold">2</span>
                            </div>
                            <span class="mt-1 font-semibold text-secondary">2. Evaluación</span>
                            <span class="text-[10px] text-muted-foreground">
                                {{ app.score ? `Score ${app.score}` : 'En análisis' }}
                            </span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div
                                class="grid h-7 w-7 place-items-center rounded-full text-white shadow-xs"
                                :class="app.documents.length >= 3 ? 'bg-emerald-500' : app.documents.length > 0 ? 'bg-amber-500' : 'bg-muted-foreground/30'"
                            >
                                <Check v-if="app.documents.length >= 3" class="h-3.5 w-3.5" />
                                <span v-else class="text-xs font-bold">3</span>
                            </div>
                            <span class="mt-1 font-semibold text-secondary">3. Expediente</span>
                            <span class="text-[10px] text-muted-foreground">
                                {{ app.documents.length }} de 3 docs
                            </span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div
                                class="grid h-7 w-7 place-items-center rounded-full text-white shadow-xs"
                                :class="
                                    app.status === 'aprobado'
                                        ? 'bg-emerald-500'
                                        : app.status === 'rechazado'
                                          ? 'bg-rose-500'
                                          : 'bg-muted-foreground/30'
                                "
                            >
                                <Check v-if="app.status === 'aprobado'" class="h-3.5 w-3.5" />
                                <X v-else-if="app.status === 'rechazado'" class="h-3.5 w-3.5" />
                                <span v-else class="text-xs font-bold">4</span>
                            </div>
                            <span class="mt-1 font-semibold text-secondary">4. Dictamen</span>
                            <span
                                class="text-[10px]"
                                :class="
                                    app.status === 'aprobado'
                                        ? 'font-bold text-emerald-600'
                                        : app.status === 'rechazado'
                                          ? 'font-bold text-rose-600'
                                          : 'text-muted-foreground'
                                "
                            >
                                {{ app.status === 'aprobado' ? 'Aprobado' : app.status === 'rechazado' ? 'Declinado' : 'Pendiente' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Parámetros Financieros -->
                <dl class="mt-4 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                    <div class="rounded-2xl bg-muted/40 p-3">
                        <dt class="text-muted-foreground">Mensualidad est.</dt>
                        <dd class="mt-0.5 text-base font-black text-primary">{{ formatPrice(app.monthly_payment) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-muted/40 p-3">
                        <dt class="text-muted-foreground">Enganche</dt>
                        <dd class="mt-0.5 text-base font-black text-secondary">{{ formatPrice(app.down_payment) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-muted/40 p-3">
                        <dt class="text-muted-foreground">Plazo</dt>
                        <dd class="mt-0.5 text-base font-black text-secondary">{{ app.term_months || 48 }} meses</dd>
                    </div>
                    <div class="rounded-2xl bg-muted/40 p-3">
                        <dt class="text-muted-foreground">Documentos</dt>
                        <dd class="mt-0.5 text-sm font-bold text-secondary">
                            <span v-if="app.documents.length" class="text-emerald-600">{{ app.documents.length }} adjuntos</span>
                            <span v-else class="text-amber-600">Sin adjuntos</span>
                        </dd>
                    </div>
                </dl>

                <!-- Documentos adjuntos a la solicitud -->
                <div v-if="app.documents.length" class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-muted-foreground">Adjuntos:</span>
                    <a
                        v-for="doc in app.documents"
                        :key="doc.slot"
                        :href="doc.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-secondary hover:border-primary hover:text-primary"
                    >
                        <FileCheck class="h-3 w-3 text-emerald-600" />
                        <span class="capitalize">{{ doc.slot.replace('_', ' ') }}</span>
                    </a>
                </div>
            </article>

            <Pagination
                :current-page="applications.current_page"
                :last-page="applications.last_page"
                base-path="/panel/tramites"
            />
        </div>

        <!-- Estado vacío -->
        <div
            v-else
            class="grid place-items-center rounded-3xl border border-dashed border-border bg-card py-16 text-center shadow-soft"
        >
            <div class="grid h-16 w-16 place-items-center rounded-3xl bg-primary/10 text-primary">
                <CreditCard class="h-8 w-8" />
            </div>
            <h2 class="mt-4 text-xl font-black text-secondary">Aún no tienes trámites de crédito</h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
                Simula y solicita financiamiento con las mejores tasas bancarias directamente para el auto que buscas.
            </p>
            <Button as="a" href="/credito" variant="hero" class="mt-6 gap-2">
                Simular mi crédito ahora
            </Button>
        </div>

        <!-- Modal Cotización Formal -->
        <div
            v-if="selectedApp"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="selectedApp = null"
        >
            <div class="w-full max-w-lg rounded-3xl border border-border bg-card p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <div>
                        <span class="font-mono text-xs font-bold text-primary">{{ selectedApp.folio }}</span>
                        <h3 class="text-lg font-black text-secondary">Cotización de Financiamiento</h3>
                    </div>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-lg text-muted-foreground hover:bg-accent"
                        @click="selectedApp = null"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Vehículo:</span>
                        <span class="font-bold text-secondary">{{ selectedApp.car }}</span>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Financiera:</span>
                        <span class="font-bold text-secondary">{{ selectedApp.financiera || 'Precalificado WeCar' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Enganche:</span>
                        <span class="font-bold text-secondary">{{ formatPrice(selectedApp.down_payment) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Plazo:</span>
                        <span class="font-bold text-secondary">{{ selectedApp.term_months || 48 }} meses</span>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Mensualidad estimada:</span>
                        <span class="text-base font-black text-primary">{{ formatPrice(selectedApp.monthly_payment) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1.5">
                        <span class="text-muted-foreground">Estatus del trámite:</span>
                        <span class="font-bold capitalize text-secondary">{{ selectedApp.status.replace('_', ' ') }}</span>
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-muted-foreground">
                    Esta cotización es de carácter informativo sujeta a validación final de la entidad crediticia. Tasa y condiciones basadas en el perfil analizado.
                </p>

                <div class="mt-6 flex gap-3">
                    <Button variant="outline" class="flex-1 gap-2" @click="printQuote">
                        <Printer class="h-4 w-4" /> Imprimir
                    </Button>
                    <Button class="flex-1" @click="selectedApp = null">
                        Cerrar
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>