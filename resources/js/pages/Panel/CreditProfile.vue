<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileText, Loader2, Upload } from '@lucide/vue';
import { toast } from 'vue-sonner';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    profile: {
        full_name: string;
        email: string;
        phone: string | null;
        birthdate: string | null;
        rfc: string | null;
        curp: string | null;
        gender: string | null;
        calle: string | null;
        colonia: string | null;
        cp: string | null;
        alcaldia: string | null;
        city: string | null;
        state: string | null;
        country: string | null;
        ocupacion: string | null;
        empresa: string | null;
        antiguedad: string | null;
        tipo_trabajo: string | null;
        ingreso_mensual: string | null;
        deuda_mensual: string | null;
        banco: string | null;
        tipo_cuenta: string | null;
        enganche_disponible: string | null;
    };
}>();

const form = useForm({
    full_name: props.profile.full_name ?? '',
    phone: props.profile.phone ?? '',
    birthdate: props.profile.birthdate ?? '',
    rfc: props.profile.rfc ?? '',
    curp: props.profile.curp ?? '',
    gender: props.profile.gender ?? '',
    calle: props.profile.calle ?? '',
    colonia: props.profile.colonia ?? '',
    cp: props.profile.cp ?? '',
    alcaldia: props.profile.alcaldia ?? '',
    city: props.profile.city ?? '',
    state: props.profile.state ?? '',
    country: props.profile.country ?? 'México',
    ocupacion: props.profile.ocupacion ?? '',
    empresa: props.profile.empresa ?? '',
    antiguedad: props.profile.antiguedad ?? '',
    tipo_trabajo: props.profile.tipo_trabajo ?? '',
    ingreso_mensual: props.profile.ingreso_mensual ?? '',
    deuda_mensual: props.profile.deuda_mensual ?? '',
    banco: props.profile.banco ?? '',
    tipo_cuenta: props.profile.tipo_cuenta ?? '',
    enganche_disponible: props.profile.enganche_disponible ?? '',
});

const completion = computed(() => {
    const fields = Object.values(form.data());
    const filled = fields.filter((value) => value !== '' && value !== null && value !== undefined).length;

    return Math.round((filled / fields.length) * 100);
});

const submit = () => form.post('/panel/perfil-crediticio', { preserveScroll: true });

// Expediente digital
const docSlots = [
    { slot: 'ine', label: 'INE / Identificación oficial' },
    { slot: 'domicilio', label: 'Comprobante de domicilio' },
    { slot: 'estado_cuenta', label: 'Estados de cuenta / Ingresos' },
];

const savedDocs = ref<Record<string, { saved: boolean; name: string | null }>>({
    ine: { saved: false, name: null },
    domicilio: { saved: false, name: null },
    estado_cuenta: { saved: false, name: null },
});
const uploadingSlot = ref<string | null>(null);

const loadUserDocuments = async () => {
    try {
        const res = await fetch('/api/credito/documentos');

        if (!res.ok) {
            return;
        }

        const data = await res.json();
        const documents = (data.documents ?? []) as { slot: string; name: string | null; available: boolean }[];

        for (const item of docSlots) {
            const document = documents.find((doc) => doc.slot === item.slot);
            savedDocs.value[item.slot] = {
                saved: Boolean(document?.available),
                name: document?.name ?? null,
            };
        }
    } catch {
        // Sin sesión o sin conexión.
    }
};

const handleUploadDoc = async (slot: string, event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';

    if (!file) {
        return;
    }

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
            const error = await res.json().catch(() => ({}));
            toast.error(error.message || 'Error al subir el documento.');
        }
    } catch {
        toast.error('Error de conexión al subir documento.');
    } finally {
        uploadingSlot.value = null;
    }
};

onMounted(() => {
    void loadUserDocuments();
});
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Perfil crediticio</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Completa tu información para agilizar tus solicitudes de crédito.
        </p>

        <div class="mt-5 rounded-2xl border border-border bg-card p-5 shadow-soft">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-secondary">Perfil completo</span>
                <span class="font-bold text-primary">{{ completion }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${completion}%` }" />
            </div>
        </div>

        <!-- Expediente digital -->
        <div class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft">
            <div>
                <h2 class="text-base font-black text-secondary">Mi Expediente Digital</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Estos documentos se guardan de forma encriptada y aceleran tus solicitudes de crédito.
                    Solo PDF (máx. 5 MB). Reutilizable en todas las solicitudes.
                </p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div
                    v-for="slot in docSlots"
                    :key="slot.slot"
                    class="flex flex-col justify-between rounded-2xl border border-border/80 bg-background/50 p-4 transition hover:border-primary/50"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 v-if="savedDocs[slot.slot]?.saved" class="h-4 w-4 shrink-0 text-emerald-600" />
                        <FileText v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span class="text-xs font-bold text-secondary">{{ slot.label }}</span>
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

                        <label class="inline-flex cursor-pointer items-center gap-1 text-xs font-bold text-primary hover:underline">
                            <Loader2 v-if="uploadingSlot === slot.slot" class="h-3.5 w-3.5 animate-spin" />
                            <Upload v-else class="h-3.5 w-3.5" />
                            <span>{{ savedDocs[slot.slot]?.saved ? 'Reemplazar' : 'Subir' }}</span>
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

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <h2 class="text-sm font-black uppercase tracking-wide text-muted-foreground">Datos personales</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <Label for="full_name">Nombre completo</Label>
                    <Input id="full_name" v-model="form.full_name" class="mt-1.5" />
                </div>
                <div>
                    <Label for="email">Correo</Label>
                    <Input id="email" :model-value="profile.email" disabled class="mt-1.5" />
                </div>
                <div>
                    <Label for="phone">Teléfono</Label>
                    <Input id="phone" v-model="form.phone" class="mt-1.5" />
                </div>
                <div>
                    <Label for="birthdate">Fecha de nacimiento</Label>
                    <Input id="birthdate" v-model="form.birthdate" type="date" class="mt-1.5" />
                </div>
                <div>
                    <Label for="rfc">RFC</Label>
                    <Input id="rfc" v-model="form.rfc" class="mt-1.5" />
                </div>
                <div>
                    <Label for="curp">CURP</Label>
                    <Input id="curp" v-model="form.curp" class="mt-1.5" />
                </div>
                <div>
                    <Label for="gender">Género</Label>
                    <select id="gender" v-model="form.gender" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Selecciona</option>
                        <option value="femenino">Femenino</option>
                        <option value="masculino">Masculino</option>
                        <option value="otro">Otro</option>
                        <option value="prefiero_no_decir">Prefiero no decir</option>
                    </select>
                </div>
            </div>

            <h2 class="mt-7 text-sm font-black uppercase tracking-wide text-muted-foreground">Domicilio</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <Label for="calle">Calle y número</Label>
                    <Input id="calle" v-model="form.calle" class="mt-1.5" />
                </div>
                <div>
                    <Label for="colonia">Colonia</Label>
                    <Input id="colonia" v-model="form.colonia" class="mt-1.5" />
                </div>
                <div>
                    <Label for="cp">Código postal</Label>
                    <Input id="cp" v-model="form.cp" maxlength="10" class="mt-1.5" />
                </div>
                <div>
                    <Label for="alcaldia">Alcaldía / Municipio</Label>
                    <Input id="alcaldia" v-model="form.alcaldia" class="mt-1.5" />
                </div>
                <div>
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" class="mt-1.5" />
                </div>
                <div>
                    <Label for="state">Estado</Label>
                    <Input id="state" v-model="form.state" class="mt-1.5" />
                </div>
                <div>
                    <Label for="country">País</Label>
                    <Input id="country" v-model="form.country" class="mt-1.5" />
                </div>
            </div>

            <h2 class="mt-7 text-sm font-black uppercase tracking-wide text-muted-foreground">Datos laborales y bancarios</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <Label for="ocupacion">Ocupación</Label>
                    <Input id="ocupacion" v-model="form.ocupacion" class="mt-1.5" placeholder="Ej. Ingeniero" />
                </div>
                <div>
                    <Label for="empresa">Empresa</Label>
                    <Input id="empresa" v-model="form.empresa" class="mt-1.5" />
                </div>
                <div>
                    <Label for="tipo_trabajo">Tipo de trabajo</Label>
                    <Input id="tipo_trabajo" v-model="form.tipo_trabajo" class="mt-1.5" placeholder="Formal / Independiente" />
                </div>
                <div>
                    <Label for="antiguedad">Antigüedad</Label>
                    <Input id="antiguedad" v-model="form.antiguedad" class="mt-1.5" placeholder="Ej. 3 años" />
                </div>
                <div>
                    <Label for="ingreso_mensual">Ingreso mensual (MXN)</Label>
                    <Input id="ingreso_mensual" v-model="form.ingreso_mensual" type="number" min="0" class="mt-1.5" />
                </div>
                <div>
                    <Label for="deuda_mensual">Deuda mensual (MXN)</Label>
                    <Input id="deuda_mensual" v-model="form.deuda_mensual" type="number" min="0" class="mt-1.5" />
                </div>
                <div>
                    <Label for="banco">Banco</Label>
                    <Input id="banco" v-model="form.banco" class="mt-1.5" />
                </div>
                <div>
                    <Label for="tipo_cuenta">Tipo de cuenta</Label>
                    <Input id="tipo_cuenta" v-model="form.tipo_cuenta" class="mt-1.5" placeholder="Nómina / Débito" />
                </div>
                <div>
                    <Label for="enganche_disponible">Enganche disponible (MXN)</Label>
                    <Input id="enganche_disponible" v-model="form.enganche_disponible" type="number" min="0" class="mt-1.5" />
                </div>
            </div>

            <Button type="submit" class="mt-6" :disabled="form.processing">Guardar perfil</Button>
        </form>
    </div>
</template>
