<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    profile: {
        full_name: string;
        phone: string | null;
        birthdate: string | null;
        rfc: string | null;
        curp: string | null;
        metadata: Record<string, string | number | null>;
    };
}>();

const form = useForm({
    ocupacion: props.profile.metadata.ocupacion ?? '',
    empresa: props.profile.metadata.empresa ?? '',
    antiguedad: props.profile.metadata.antiguedad ?? '',
    tipo_trabajo: props.profile.metadata.tipo_trabajo ?? '',
    ingreso_mensual: props.profile.metadata.ingreso_mensual ?? '',
    deuda_mensual: props.profile.metadata.deuda_mensual ?? '',
    banco: props.profile.metadata.banco ?? '',
    tipo_cuenta: props.profile.metadata.tipo_cuenta ?? '',
    enganche_disponible: props.profile.metadata.enganche_disponible ?? '',
});

const completion = computed(() => {
    const fields = Object.values(form.data());
    const filled = fields.filter((value) => value !== '' && value !== null && value !== undefined).length;
    return Math.round((filled / fields.length) * 100);
});

const submit = () => form.post('/panel/perfil-crediticio', { preserveScroll: true });
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

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
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
