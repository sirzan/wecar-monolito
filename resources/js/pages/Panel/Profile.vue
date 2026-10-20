<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { usePushNotifications } from '@/composables/usePush';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        phone: string | null;
        city: string | null;
        state: string | null;
        birthdate: string | null;
        rfc: string | null;
        curp: string | null;
        two_factor_enabled: boolean;
    };
}>();

const form = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    city: props.profile.city ?? '',
    state: props.profile.state ?? '',
    birthdate: props.profile.birthdate ?? '',
    rfc: props.profile.rfc ?? '',
    curp: props.profile.curp ?? '',
});

const submit = () => form.post('/panel/configuracion', { preserveScroll: true });

const push = usePushNotifications();
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Configuración</h1>
        <p class="mt-1 text-sm text-muted-foreground">Actualiza tus datos personales y de contacto.</p>

        <form class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <Label for="name">Nombre completo</Label>
                    <Input id="name" v-model="form.name" class="mt-1.5" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
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
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" class="mt-1.5" />
                </div>
                <div>
                    <Label for="state">Estado</Label>
                    <Input id="state" v-model="form.state" class="mt-1.5" />
                </div>
                <div>
                    <Label for="rfc">RFC</Label>
                    <Input id="rfc" v-model="form.rfc" class="mt-1.5" />
                </div>
                <div>
                    <Label for="curp">CURP</Label>
                    <Input id="curp" v-model="form.curp" class="mt-1.5" />
                </div>
            </div>
            <Button type="submit" class="mt-6" :disabled="form.processing">Guardar cambios</Button>
        </form>

        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-border bg-card p-5 shadow-soft">
            <ShieldCheck class="mt-0.5 h-5 w-5 text-success" />
            <div>
                <p class="font-bold text-secondary">Seguridad</p>
                <p class="text-sm text-muted-foreground">
                    {{ profile.two_factor_enabled ? 'La verificación en dos pasos está activa.' : 'Activa la verificación en dos pasos desde tu cuenta.' }}
                </p>
                <Button v-if="push.supported.value" variant="outline" size="sm" class="mt-3" :disabled="push.loading.value" @click="push.subscribed.value ? push.unsubscribe() : push.subscribe()">
                    {{ push.subscribed.value ? 'Desactivar notificaciones push' : 'Activar notificaciones push' }}
                </Button>
            </div>
        </div>
    </div>
</template>
